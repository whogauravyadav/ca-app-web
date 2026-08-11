<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRead extends Model
{
    protected $fillable = ['user_id', 'app_notification_id', 'read_at'];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(AppNotification::class, 'app_notification_id');
    }
}
