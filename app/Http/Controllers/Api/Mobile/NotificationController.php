<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\DeviceToken;
use App\Models\NotificationRead;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function registerToken(Request $request)
    {
        $data = $request->validate([
            'token' => 'required|string|max:512',
            'platform' => 'nullable|string|max:30',
        ]);

        $user = $request->user('sanctum');

        $token = DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $user?->id,
                'platform' => $data['platform'] ?? 'android',
                'last_used_at' => now(),
            ]
        );

        if ($user && $token->user_id !== $user->id) {
            $token->update(['user_id' => $user->id]);
        }

        return response()->json(['success' => true, 'data' => $token]);
    }

    public function unregisterToken(Request $request)
    {
        $data = $request->validate(['token' => 'required|string']);
        DeviceToken::where('token', $data['token'])->delete();

        return response()->json(['success' => true]);
    }

    public function index(Request $request)
    {
        $userId = optional($request->user('sanctum'))->id;
        $items = AppNotification::query()
            ->latest()
            ->limit(50)
            ->get();

        $readIds = [];
        if ($userId) {
            $readIds = NotificationRead::where('user_id', $userId)
                ->whereIn('app_notification_id', $items->pluck('id'))
                ->pluck('app_notification_id')
                ->all();
        }

        $data = $items->map(function (AppNotification $n) use ($readIds) {
            return [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'type' => $n->type,
                'reference_type' => $n->reference_type,
                'reference_id' => $n->reference_id,
                'data' => $n->data ?? [],
                'is_read' => in_array($n->id, $readIds, true),
                'created_at' => optional($n->created_at)?->toIso8601String(),
            ];
        });

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function unreadCount(Request $request)
    {
        $user = $request->user('sanctum');
        if (! $user) {
            $count = AppNotification::query()->where('created_at', '>=', now()->subDays(7))->count();

            return response()->json(['success' => true, 'data' => ['unread' => $count]]);
        }

        $readIds = NotificationRead::where('user_id', $user->id)->pluck('app_notification_id');
        $unread = AppNotification::whereNotIn('id', $readIds)->count();

        return response()->json(['success' => true, 'data' => ['unread' => $unread]]);
    }

    public function markRead(Request $request, int $id)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Login required'], 401);
        }

        AppNotification::findOrFail($id);
        NotificationRead::firstOrCreate(
            ['user_id' => $user->id, 'app_notification_id' => $id],
            ['read_at' => now()]
        );

        return response()->json(['success' => true]);
    }

    public function markAllRead(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Login required'], 401);
        }

        $ids = AppNotification::query()->pluck('id');
        foreach ($ids as $nid) {
            NotificationRead::firstOrCreate(
                ['user_id' => $user->id, 'app_notification_id' => $nid],
                ['read_at' => now()]
            );
        }

        return response()->json(['success' => true]);
    }
}
