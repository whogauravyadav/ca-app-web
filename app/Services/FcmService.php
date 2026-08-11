<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FcmService
{
    public function isConfigured(): bool
    {
        return filled(config('services.fcm.server_key'))
            || (filled(config('services.fcm.project_id')) && filled(config('services.fcm.credentials')));
    }

    /**
     * @param  array<string, string>  $data
     * @return array{success:int,failure:int}
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === []) {
            return ['success' => 0, 'failure' => 0];
        }

        if (! $this->isConfigured()) {
            Log::warning('FCM not configured; skipping push send');

            return ['success' => 0, 'failure' => count($tokens)];
        }

        $success = 0;
        $failure = 0;

        foreach (array_chunk($tokens, 500) as $chunk) {
            $result = $this->dispatch($chunk, $title, $body, $data);
            $success += $result['success'];
            $failure += $result['failure'];
        }

        return compact('success', 'failure');
    }

    /**
     * @param  array<string, string>  $data
     * @return array{success:int,failure:int}
     */
    public function sendToTopic(string $topic, string $title, string $body, array $data = []): array
    {
        if (! $this->isConfigured()) {
            Log::warning('FCM not configured; skipping topic push');

            return ['success' => 0, 'failure' => 1];
        }

        if (filled(config('services.fcm.server_key'))) {
            return $this->sendLegacy(null, $topic, $title, $body, $data);
        }

        return $this->sendHttpV1(null, $topic, $title, $body, $data);
    }

    /**
     * @param  list<string>|null  $tokens
     * @param  array<string, string>  $data
     * @return array{success:int,failure:int}
     */
    private function dispatch(?array $tokens, string $title, string $body, array $data): array
    {
        if (filled(config('services.fcm.server_key'))) {
            return $this->sendLegacy($tokens, null, $title, $body, $data);
        }

        return $this->sendHttpV1($tokens, null, $title, $body, $data);
    }

    /**
     * Legacy FCM HTTP API (server key).
     *
     * @param  list<string>|null  $tokens
     * @param  array<string, string>  $data
     * @return array{success:int,failure:int}
     */
    private function sendLegacy(?array $tokens, ?string $topic, string $title, string $body, array $data): array
    {
        $payload = [
            'priority' => 'high',
            'notification' => [
                'title' => $title,
                'body' => $body,
                'sound' => 'default',
            ],
            'data' => array_map('strval', $data),
        ];

        if ($topic) {
            $payload['to'] = '/topics/'.$topic;
        } else {
            $payload['registration_ids'] = $tokens;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'key='.config('services.fcm.server_key'),
                'Content-Type' => 'application/json',
            ])->timeout(20)->post('https://fcm.googleapis.com/fcm/send', $payload);

            if (! $response->successful()) {
                Log::error('FCM legacy send failed', ['body' => $response->body()]);

                return ['success' => 0, 'failure' => $tokens ? count($tokens) : 1];
            }

            $json = $response->json();
            $success = (int) ($json['success'] ?? ($topic ? 1 : 0));
            $failure = (int) ($json['failure'] ?? 0);

            // Drop invalid tokens
            foreach ($json['results'] ?? [] as $i => $row) {
                if (! empty($row['error']) && isset($tokens[$i])) {
                    if (in_array($row['error'], ['NotRegistered', 'InvalidRegistration'], true)) {
                        DeviceToken::where('token', $tokens[$i])->delete();
                    }
                }
            }

            return compact('success', 'failure');
        } catch (\Throwable $e) {
            Log::error('FCM legacy exception: '.$e->getMessage());

            return ['success' => 0, 'failure' => $tokens ? count($tokens) : 1];
        }
    }

    /**
     * FCM HTTP v1 (service account).
     *
     * @param  list<string>|null  $tokens
     * @param  array<string, string>  $data
     * @return array{success:int,failure:int}
     */
    private function sendHttpV1(?array $tokens, ?string $topic, string $title, string $body, array $data): array
    {
        $accessToken = $this->getAccessToken();
        if (! $accessToken) {
            return ['success' => 0, 'failure' => $tokens ? count($tokens) : 1];
        }

        $projectId = config('services.fcm.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $success = 0;
        $failure = 0;

        $targets = $topic
            ? [['topic' => $topic]]
            : array_map(fn ($t) => ['token' => $t], $tokens ?? []);

        foreach ($targets as $target) {
            $message = [
                'message' => array_merge($target, [
                    'notification' => [
                        'title' => $title,
                        'body' => $body,
                    ],
                    'data' => array_map('strval', $data),
                    'android' => [
                        'priority' => 'HIGH',
                        'notification' => [
                            'sound' => 'default',
                            'channel_id' => 'ca_default',
                        ],
                    ],
                ]),
            ];

            try {
                $response = Http::withToken($accessToken)
                    ->timeout(20)
                    ->post($url, $message);

                if ($response->successful()) {
                    $success++;
                } else {
                    $failure++;
                    $err = $response->json('error.status') ?? $response->body();
                    Log::warning('FCM v1 send failed', ['error' => $err]);
                    if (isset($target['token']) && Str::contains((string) $err, ['UNREGISTERED', 'INVALID_ARGUMENT'])) {
                        DeviceToken::where('token', $target['token'])->delete();
                    }
                }
            } catch (\Throwable $e) {
                $failure++;
                Log::error('FCM v1 exception: '.$e->getMessage());
            }
        }

        return compact('success', 'failure');
    }

    private function getAccessToken(): ?string
    {
        $path = config('services.fcm.credentials');
        if (! $path || ! is_readable($path)) {
            Log::error('FCM credentials file missing', ['path' => $path]);

            return null;
        }

        $creds = json_decode((string) file_get_contents($path), true);
        if (! is_array($creds)) {
            return null;
        }

        $now = time();
        $header = $this->b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $claim = $this->b64url(json_encode([
            'iss' => $creds['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        $unsigned = $header.'.'.$claim;
        $key = openssl_pkey_get_private($creds['private_key']);
        if (! $key) {
            return null;
        }
        openssl_sign($unsigned, $signature, $key, OPENSSL_ALGO_SHA256);
        $jwt = $unsigned.'.'.$this->b64url($signature);

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt,
        ]);

        if (! $response->successful()) {
            Log::error('FCM OAuth token failed', ['body' => $response->body()]);

            return null;
        }

        return $response->json('access_token');
    }

    private function b64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
