<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\NotificationSetting;
use App\Services\FcmService;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;

class NotificationAdminController extends Controller
{
    public function index()
    {
        $items = AppNotification::query()->latest()->paginate(30);

        return response()->json([
            'success' => true,
            'data' => $items->items(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function settings()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'notify_on_article_publish' => NotificationSetting::enabled('notify_on_article_publish'),
                'notify_on_quiz_publish' => NotificationSetting::enabled('notify_on_quiz_publish'),
                'fcm_topic' => NotificationSetting::getValue('fcm_topic', 'all_users'),
                'fcm_configured' => app(FcmService::class)->isConfigured(),
                'device_tokens' => DeviceToken::count(),
            ],
        ]);
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'notify_on_article_publish' => 'sometimes|boolean',
            'notify_on_quiz_publish' => 'sometimes|boolean',
            'fcm_topic' => 'sometimes|string|max:100',
        ]);

        if (array_key_exists('notify_on_article_publish', $data)) {
            NotificationSetting::setValue(
                'notify_on_article_publish',
                $data['notify_on_article_publish'] ? '1' : '0'
            );
        }
        if (array_key_exists('notify_on_quiz_publish', $data)) {
            NotificationSetting::setValue(
                'notify_on_quiz_publish',
                $data['notify_on_quiz_publish'] ? '1' : '0'
            );
        }
        if (! empty($data['fcm_topic'])) {
            NotificationSetting::setValue('fcm_topic', $data['fcm_topic']);
        }

        return $this->settings();
    }

    public function send(Request $request, NotificationDispatcher $dispatcher)
    {
        $data = $request->validate([
            'title' => 'required|string|max:120',
            'body' => 'required|string|max:500',
        ]);

        $notification = $dispatcher->sendCustom(
            $data['title'],
            $data['body'],
            [],
            $request->user()->id,
        );

        return response()->json(['success' => true, 'data' => $notification], 201);
    }
}
