<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class KtatvaStorageService
{
    public function configured(): bool
    {
        return filled(config('ktatva.api_key')) && filled(config('ktatva.bucket_id'));
    }

    /**
     * Upload a file. Returns ['object_key' => string, 'url' => ?string, 'raw' => array]
     */
    public function upload(UploadedFile $file, ?string $objectKey = null, string $folder = 'articles'): array
    {
        $this->assertConfigured();

        $ext = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $objectKey ??= trim($folder, '/').'/'.now()->format('Y/m/d').'/'.Str::uuid().'.'.$ext;

        $response = Http::withHeaders($this->authHeaders())
            ->acceptJson()
            ->timeout(60)
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post(rtrim(config('ktatva.base_url'), '/').'/upload', [
                'storage_bucket_id' => config('ktatva.bucket_id'),
                'object_key' => $objectKey,
            ]);

        if (! $response->successful()) {
            Log::error('Ktatva upload failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Storage upload failed: '.$response->json('message', $response->body()));
        }

        $data = $response->json() ?? [];
        $resolvedKey = $this->extractObjectKey($data, $objectKey);
        $url = $this->extractUrl($data);

        if (! $url && $resolvedKey) {
            try {
                $url = $this->downloadUrl($resolvedKey);
            } catch (\Throwable $e) {
                Log::warning('Ktatva download-url after upload failed', ['error' => $e->getMessage()]);
            }
        }

        return [
            'object_key' => $resolvedKey,
            'url' => $url,
            'raw' => $data,
        ];
    }

    /**
     * Download a remote image and upload it to Ktatva. Returns the same shape as upload().
     */
    public function uploadFromUrl(string $url, string $folder = 'articles'): array
    {
        $response = Http::withHeaders([
            'User-Agent' => config('outsource_1.user_agent', 'Mozilla/5.0'),
            'Accept' => 'image/*,*/*',
        ])->timeout(45)->get($url);

        if (! $response->successful() || $response->body() === '') {
            throw new RuntimeException('Failed to download image (HTTP '.$response->status().')');
        }

        $mime = strtolower((string) $response->header('Content-Type'));
        $ext = match (true) {
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'webp') => 'webp',
            str_contains($mime, 'gif') => 'gif',
            str_contains($mime, 'jpeg'), str_contains($mime, 'jpg') => 'jpg',
            default => pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg',
        };
        $ext = strtolower(preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpg');

        $tmpPath = tempnam(sys_get_temp_dir(), 'ktimg_');
        if ($tmpPath === false) {
            throw new RuntimeException('Could not create temp file for image upload');
        }
        $named = $tmpPath.'.'.$ext;
        rename($tmpPath, $named);
        file_put_contents($named, $response->body());

        $file = new UploadedFile($named, 'featured.'.$ext, $mime ?: 'image/jpeg', null, true);

        try {
            return $this->upload($file, null, $folder);
        } finally {
            @unlink($named);
        }
    }

    /**
     * Get a (often signed) download URL for an object key.
     */
    public function downloadUrl(string $objectKey, bool $useCache = true): string
    {
        $this->assertConfigured();

        $objectKey = $this->normalizeObjectKey($objectKey);
        if ($objectKey === '') {
            throw new RuntimeException('Empty object key');
        }

        // Already a full URL
        if (Str::startsWith($objectKey, ['http://', 'https://'])) {
            return $objectKey;
        }

        $cacheKey = 'ktatva:url:'.sha1($objectKey);
        if ($useCache && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $response = Http::withHeaders($this->authHeaders())
            ->acceptJson()
            ->timeout(30)
            ->get(rtrim(config('ktatva.base_url'), '/').'/download-url', [
                'object_key' => $objectKey,
                'storage_bucket_id' => config('ktatva.bucket_id'),
            ]);

        if (! $response->successful()) {
            Log::error('Ktatva download-url failed', [
                'status' => $response->status(),
                'body' => $response->body(),
                'object_key' => $objectKey,
            ]);
            throw new RuntimeException('Storage download-url failed: '.$response->json('message', $response->body()));
        }

        $data = $response->json() ?? [];
        $url = $this->extractUrl($data);

        if (! $url) {
            throw new RuntimeException('Storage download-url response missing URL');
        }

        Cache::put($cacheKey, $url, config('ktatva.url_cache_ttl', 1800));

        return $url;
    }

    /**
     * Resolve a stored featured_image value (object key or URL) to a browser-usable URL.
     */
    public function resolvePublicUrl(?string $stored): ?string
    {
        if (! filled($stored)) {
            return null;
        }

        if (Str::startsWith($stored, ['http://', 'https://'])) {
            return $stored;
        }

        if (! $this->configured()) {
            return $stored;
        }

        try {
            return $this->downloadUrl($stored);
        } catch (\Throwable $e) {
            Log::warning('Ktatva resolvePublicUrl failed', [
                'key' => $stored,
                'error' => $e->getMessage(),
            ]);

            return $stored;
        }
    }

    private function authHeaders(): array
    {
        $key = config('ktatva.api_key');

        return [
            'X-API-Key' => $key,
            'Authorization' => 'Bearer '.$key,
        ];
    }

    private function assertConfigured(): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('Ktatva Storage is not configured (KTATVA_STORAGE_API_KEY / KTATVA_STORAGE_BUCKET_ID)');
        }
    }

    private function normalizeObjectKey(string $key): string
    {
        return ltrim(trim($key), '/');
    }

    private function extractObjectKey(array $data, string $fallback): string
    {
        $candidates = [
            data_get($data, 'object_key'),
            data_get($data, 'data.object_key'),
            data_get($data, 'key'),
            data_get($data, 'data.key'),
            data_get($data, 'path'),
            data_get($data, 'data.path'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $this->normalizeObjectKey($candidate);
            }
        }

        return $this->normalizeObjectKey($fallback);
    }

    private function extractUrl(array $data): ?string
    {
        $candidates = [
            data_get($data, 'url'),
            data_get($data, 'download_url'),
            data_get($data, 'signed_url'),
            data_get($data, 'data.url'),
            data_get($data, 'data.download_url'),
            data_get($data, 'data.signed_url'),
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && Str::startsWith($candidate, ['http://', 'https://'])) {
                return $candidate;
            }
        }

        return null;
    }
}
