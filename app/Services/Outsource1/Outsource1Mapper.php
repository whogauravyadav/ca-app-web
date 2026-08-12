<?php

namespace App\Services\Outsource1;

use App\Models\Category;
use Illuminate\Support\Str;

class Outsource1Mapper
{
    /**
     * @var array<string, string> lowercase needle => category slug
     */
    private array $topicToSlug = [
        'polity' => 'polity',
        'constitution' => 'polity',
        'government' => 'polity',
        'india' => 'polity',
        'politics' => 'polity',
        'economy' => 'economy',
        'economic' => 'economy',
        'banking' => 'banking',
        'business' => 'economy',
        'finance' => 'economy',
        'science' => 'science-tech',
        'technology' => 'science-tech',
        's&t' => 'science-tech',
        'environment' => 'environment',
        'ecology' => 'environment',
        'biodiversity' => 'environment',
        'international' => 'international',
        'world' => 'international',
        'defence' => 'defence',
        'defense' => 'defence',
        'scheme' => 'government-schemes',
        'report' => 'reports-indices',
        'indice' => 'reports-indices',
        'award' => 'awards',
        'honour' => 'awards',
        'person' => 'awards',
        'place' => 'places-in-news',
        'day' => 'important-days',
        'event' => 'important-days',
        'sport' => 'sports',
        'summit' => 'summits',
        'conference' => 'summits',
        'art' => 'art-culture',
        'culture' => 'art-culture',
        'ancient' => 'ancient-history',
        'medieval' => 'medieval-history',
        'modern' => 'modern-history',
        'freedom' => 'modern-history',
        'indian geography' => 'indian-geography',
        'world geography' => 'world-geography',
        'geography' => 'indian-geography',
        'biology' => 'biology',
        'physics' => 'physics',
        'chemistry' => 'chemistry',
        'general science' => 'general-science',
        'world history' => 'world-history',
        'books' => 'books-authors',
        'author' => 'books-authors',
        'daily' => 'daily-ca',
        'current affairs' => 'daily-ca',
    ];

    public function ensureCategories(): void
    {
        (new \Database\Seeders\Outsource1CategorySeeder)->run();
    }

    public function categoryIdFor(?string $topic, string $fallbackSlug = 'daily-ca'): int
    {
        $slug = $this->slugFor($topic) ?? $fallbackSlug;
        $cat = Category::where('slug', $slug)->first()
            ?? Category::where('slug', $fallbackSlug)->first()
            ?? Category::orderBy('id')->first();

        if (! $cat) {
            $cat = Category::create([
                'name' => Str::title(str_replace('-', ' ', $slug)),
                'slug' => $slug,
                'is_active' => true,
                'sort_order' => 99,
            ]);
        }

        return $cat->id;
    }

    public function slugFor(?string $topic): ?string
    {
        if (! $topic) {
            return null;
        }
        $hay = strtolower(trim($topic));
        if (Category::where('slug', $hay)->exists()) {
            return $hay;
        }
        $map = $this->topicToSlug;
        uksort($map, fn ($a, $b) => strlen($b) <=> strlen($a));
        foreach ($map as $needle => $slug) {
            if (str_contains($hay, $needle)) {
                return $slug;
            }
        }

        return null;
    }

    public function summaryFromBody(string $html, int $max = 280): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');

        return Str::limit($text, $max, '…');
    }

    public function readTimeMinutes(string $html): int
    {
        $words = str_word_count(strip_tags($html));

        return max(3, min(60, (int) ceil($words / 200)));
    }
}
