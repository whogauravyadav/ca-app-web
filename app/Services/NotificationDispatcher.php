<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Article;
use App\Models\DeviceToken;
use App\Models\NotificationSetting;
use App\Models\Quiz;
use Illuminate\Support\Facades\Log;

class NotificationDispatcher
{
    public function __construct(private FcmService $fcm) {}

    public function notifyArticlePublished(Article $article, ?int $createdBy = null): ?AppNotification
    {
        if (! NotificationSetting::enabled('notify_on_article_publish')) {
            return null;
        }

        $title = 'New article published';
        $body = $article->title;
        $data = [
            'type' => 'article',
            'article_id' => (string) $article->id,
            'slug' => (string) $article->slug,
            'route' => '/article/'.$article->slug,
        ];

        return $this->dispatch(
            title: $title,
            body: $body,
            type: 'article',
            referenceType: Article::class,
            referenceId: $article->id,
            data: $data,
            createdBy: $createdBy,
        );
    }

    public function notifyQuizPublished(Quiz $quiz, ?int $createdBy = null): ?AppNotification
    {
        if (! NotificationSetting::enabled('notify_on_quiz_publish')) {
            return null;
        }

        $title = 'New quiz available';
        $body = $quiz->title;
        $data = [
            'type' => 'quiz',
            'quiz_id' => (string) $quiz->id,
            'route' => '/quiz/'.$quiz->id,
        ];

        return $this->dispatch(
            title: $title,
            body: $body,
            type: 'quiz',
            referenceType: Quiz::class,
            referenceId: $quiz->id,
            data: $data,
            createdBy: $createdBy,
        );
    }

    /**
     * @param  array<string, string>  $data
     */
    public function sendCustom(
        string $title,
        string $body,
        array $data = [],
        ?int $createdBy = null,
    ): AppNotification {
        return $this->dispatch(
            title: $title,
            body: $body,
            type: 'custom',
            referenceType: null,
            referenceId: null,
            data: array_merge(['type' => 'custom'], $data),
            createdBy: $createdBy,
        );
    }

    /**
     * @param  array<string, string>  $data
     */
    private function dispatch(
        string $title,
        string $body,
        string $type,
        ?string $referenceType,
        ?int $referenceId,
        array $data,
        ?int $createdBy,
    ): AppNotification {
        $notification = AppNotification::create([
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'data' => $data,
            'created_by' => $createdBy,
        ]);

        $data['notification_id'] = (string) $notification->id;

        $topic = NotificationSetting::getValue('fcm_topic', 'all_users') ?: 'all_users';
        $tokenCount = DeviceToken::query()->count();

        // Broadcast on FCM topic (mobile app subscribes to all_users).
        $result = $this->fcm->sendToTopic($topic, $title, $body, $data);

        $success = $result['success'];
        $failure = $result['failure'];

        $notification->update([
            'data' => array_merge($data, ['device_tokens' => $tokenCount]),
            'fcm_success' => $success,
            'fcm_failure' => $failure,
            'sent_via_fcm' => $this->fcm->isConfigured(),
        ]);

        Log::info('Notification dispatched', [
            'id' => $notification->id,
            'type' => $type,
            'fcm_success' => $success,
            'fcm_failure' => $failure,
        ]);

        return $notification->fresh();
    }
}
