<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'exams',
        'password',
        'role',
        'subscription_status',
        'subscription_expires_at',
        'google_id',
        'streak_count',
        'last_active_date',
        'avatar_url',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'subscription_expires_at' => 'datetime',
            'last_active_date' => 'date',
            'exams' => 'array',
        ];
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'editor'], true);
    }

    public function isAdFree(): bool
    {
        return $this->subscription_status === 'active'
            && $this->subscription_expires_at
            && $this->subscription_expires_at->isFuture();
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function toApiArray(): array
    {
        $examKeys = array_values(array_filter($this->exams ?? []));
        $labels = config('exams.options', []);
        $examLabels = array_map(
            fn ($key) => $labels[$key] ?? $key,
            $examKeys
        );

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'exams' => $examKeys,
            'exam_labels' => array_values($examLabels),
            'role' => $this->role,
            'subscription_status' => $this->isAdFree() ? 'active' : ($this->subscription_status === 'expired' ? 'expired' : 'free'),
            'subscription_expires_at' => $this->subscription_expires_at?->toIso8601String(),
            'streak_count' => $this->streak_count,
            'avatar_url' => $this->avatar_url,
            'is_ad_free' => $this->isAdFree(),
        ];
    }
}
