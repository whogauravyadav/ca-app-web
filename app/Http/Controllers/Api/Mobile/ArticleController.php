<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use Illuminate\Http\Request;

class ArticleController extends Controller
{
    public function categories()
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'slug', 'icon', 'color']);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    public function index(Request $request)
    {
        $query = Article::query()
            ->published()
            ->with('category:id,name,slug,color')
            ->latest('published_at');

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        if ($request->filled('search')) {
            $term = '%'.$request->search.'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)->orWhere('summary', 'like', $term);
            });
        }

        if ($request->boolean('today')) {
            $query->whereDate('published_at', today());
        }

        $user = $request->user('sanctum');
        $isPremium = $user && $user->isAdFree();

        // Hide premium-early articles from free users until published_at is past "early" window
        // For v1: is_premium_early articles are still listed but flagged
        $articles = $query->paginate((int) $request->get('per_page', 15));

        $articles->getCollection()->transform(function (Article $article) use ($isPremium) {
            return [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'summary' => $article->summary,
                'featured_image' => $article->featuredImageUrl(),
                'featured_image_key' => $article->featured_image,
                'read_time_min' => $article->read_time_min,
                'published_at' => $article->published_at?->toIso8601String(),
                'is_premium_early' => $article->is_premium_early,
                'category' => $article->category,
                'locked' => $article->is_premium_early && ! $isPremium
                    && $article->published_at && $article->published_at->isFuture(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $articles->items(),
            'meta' => [
                'current_page' => $articles->currentPage(),
                'last_page' => $articles->lastPage(),
                'total' => $articles->total(),
            ],
        ]);
    }

    public function show(Request $request, string $slug)
    {
        $article = Article::query()
            ->published()
            ->with(['category:id,name,slug,color', 'quizzes' => fn ($q) => $q->published()->select('id', 'title', 'article_id')])
            ->where('slug', $slug)
            ->firstOrFail();

        $bookmarked = false;
        if ($user = $request->user('sanctum')) {
            $bookmarked = $user->bookmarks()->where('article_id', $article->id)->exists();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $article->id,
                'title' => $article->title,
                'slug' => $article->slug,
                'summary' => $article->summary,
                'body' => $article->body,
                'featured_image' => $article->featuredImageUrl(),
                'featured_image_key' => $article->featured_image,
                'read_time_min' => $article->read_time_min,
                'published_at' => $article->published_at?->toIso8601String(),
                'is_premium_early' => $article->is_premium_early,
                'category' => $article->category,
                'quizzes' => $article->quizzes,
                'is_bookmarked' => $bookmarked,
            ],
        ]);
    }
}
