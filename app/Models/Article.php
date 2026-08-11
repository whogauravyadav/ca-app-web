<?php

namespace App\Models;

use App\Services\KtatvaStorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Article extends Model
{
    protected $fillable = [
        'title', 'slug', 'summary', 'body', 'category_id', 'author_id',
        'featured_image', 'read_time_min', 'status', 'published_at', 'is_premium_early',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'is_premium_early' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Article $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title).'-'.Str::random(5);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Stored value is a Ktatva object_key (or legacy local/http URL).
     * Returns a browser-usable download URL.
     */
    public function featuredImageUrl(): ?string
    {
        return app(KtatvaStorageService::class)->resolvePublicUrl($this->featured_image);
    }
}
