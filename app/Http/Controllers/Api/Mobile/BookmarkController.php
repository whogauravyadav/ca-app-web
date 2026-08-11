<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Bookmark;
use App\Models\ReadingProgress;
use Illuminate\Http\Request;

class BookmarkController extends Controller
{
    public function index(Request $request)
    {
        $bookmarks = $request->user()
            ->bookmarks()
            ->with(['article.category:id,name,slug,color'])
            ->latest()
            ->get()
            ->map(fn (Bookmark $b) => [
                'id' => $b->id,
                'article' => $b->article ? [
                    'id' => $b->article->id,
                    'title' => $b->article->title,
                    'slug' => $b->article->slug,
                    'summary' => $b->article->summary,
                    'featured_image' => $b->article->featuredImageUrl(),
                    'read_time_min' => $b->article->read_time_min,
                    'category' => $b->article->category,
                ] : null,
                'created_at' => $b->created_at?->toIso8601String(),
            ]);

        return response()->json(['success' => true, 'data' => $bookmarks]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'article_id' => 'required|exists:articles,id',
        ]);

        $bookmark = Bookmark::firstOrCreate([
            'user_id' => $request->user()->id,
            'article_id' => $data['article_id'],
        ]);

        return response()->json(['success' => true, 'data' => $bookmark], 201);
    }

    public function destroy(Request $request, int $articleId)
    {
        $request->user()->bookmarks()->where('article_id', $articleId)->delete();

        return response()->json(['success' => true, 'message' => 'Removed']);
    }
}
