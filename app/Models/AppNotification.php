<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AppNotification extends Model
{
    protected $table = 'app_notifications';

    protected $fillable = [
        'title',
        'body',
        'type',
        'reference_type',
        'reference_id',
        'data',
        'fcm_success',
        'fcm_failure',
        'sent_via_fcm',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'sent_via_fcm' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reads(): HasMany
    {
        return $this->hasMany(NotificationRead::class, 'app_notification_id');
    }

    public function toApiArray(?int $userId = null): array
    {
        $isRead = false;
        if ($userId) {
            $isRead = $this->reads->firstWhere('user_id', $userId) !== null
                || $this->relationLoaded('reads') && $this->reads->contains('user_id', $userId);
        }

        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
            'type' => $this->type,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'data' => $this->data ?? [],
            'is_read' => $isRead,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
