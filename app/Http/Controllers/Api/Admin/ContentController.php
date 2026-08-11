<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Subscription;
use App\Models\User;
use App\Services\KtatvaStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ContentController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'articles' => Article::count(),
                'published_articles' => Article::where('status', 'published')->count(),
                'quizzes' => Quiz::count(),
                'users' => User::where('role', 'student')->count(),
                'active_subscribers' => User::where('subscription_status', 'active')
                    ->where('subscription_expires_at', '>', now())->count(),
                'quiz_attempts' => QuizAttempt::count(),
                'recent_attempts' => QuizAttempt::with(['user:id,name', 'quiz:id,title'])
                    ->latest()->limit(8)->get(),
            ],
        ]);
    }

    // Categories
    public function categories()
    {
        return response()->json(['success' => true, 'data' => Category::orderBy('sort_order')->get()]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:120|unique:categories,slug',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);
        $cat = Category::create($data);

        return response()->json(['success' => true, 'data' => $cat], 201);
    }

    public function updateCategory(Request $request, int $id)
    {
        $cat = Category::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:100',
            'slug' => 'sometimes|string|max:120|unique:categories,slug,'.$id,
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);
        $cat->update($data);

        return response()->json(['success' => true, 'data' => $cat]);
    }

    public function destroyCategory(int $id)
    {
        Category::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    // Articles
    public function articles(Request $request)
    {
        $q = Article::with('category:id,name')->latest();
        if ($request->filled('status')) {
            $q->where('status', $request->status);
        }
        $articles = $q->paginate(20);

        $items = collect($articles->items())->map(function (Article $article) {
            $row = $article->toArray();
            $row['featured_image_key'] = $article->featured_image;
            $row['featured_image_url'] = $article->featuredImageUrl();
            $row['featured_image'] = $row['featured_image_url'];

            return $row;
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => ['current_page' => $articles->currentPage(), 'last_page' => $articles->lastPage(), 'total' => $articles->total()],
        ]);
    }

    public function storeArticle(Request $request)
    {
        $data = $this->validateArticle($request);
        $data['author_id'] = $request->user()->id;
        $data['slug'] = $data['slug'] ?? Str::slug($data['title']).'-'.Str::random(4);
        if (($data['status'] ?? 'draft') === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        $article = Article::create($data);

        return response()->json([
            'success' => true,
            'data' => $this->presentArticle($article->load('category')),
        ], 201);
    }

    public function updateArticle(Request $request, int $id)
    {
        $article = Article::findOrFail($id);
        $data = $this->validateArticle($request, true);
        if (($data['status'] ?? null) === 'published' && ! $article->published_at && empty($data['published_at'])) {
            $data['published_at'] = now();
        }
        $article->update($data);

        return response()->json([
            'success' => true,
            'data' => $this->presentArticle($article->fresh('category')),
        ]);
    }

    public function destroyArticle(int $id)
    {
        Article::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }

    public function publishArticle(int $id)
    {
        $article = Article::findOrFail($id);
        $article->update([
            'status' => 'published',
            'published_at' => $article->published_at ?? now(),
        ]);

        return response()->json(['success' => true, 'data' => $article]);
    }

    public function uploadImage(Request $request, KtatvaStorageService $storage)
    {
        $request->validate(['image' => 'required|image|max:5120']);

        $result = $storage->upload(
            $request->file('image'),
            null,
            config('ktatva.prefix', 'articles')
        );

        return response()->json([
            'success' => true,
            // Persist object_key on the article; url is for immediate preview
            'object_key' => $result['object_key'],
            'url' => $result['url'],
            'path' => $result['object_key'],
        ]);
    }

    private function presentArticle(Article $article): array
    {
        $row = $article->toArray();
        $row['featured_image_key'] = $article->featured_image;
        $row['featured_image_url'] = $article->featuredImageUrl();
        $row['featured_image'] = $row['featured_image_url'];

        return $row;
    }

    private function validateArticle(Request $request, bool $partial = false): array
    {
        $rules = [
            'title' => ($partial ? 'sometimes|' : '').'required|string|max:255',
            'slug' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'body' => ($partial ? 'sometimes|' : '').'required|string',
            'category_id' => ($partial ? 'sometimes|' : '').'required|exists:categories,id',
            'featured_image' => 'nullable|string|max:500', // Ktatva object_key
            'read_time_min' => 'nullable|integer|min:1|max:60',
            'status' => 'nullable|in:draft,published',
            'published_at' => 'nullable|date',
            'is_premium_early' => 'nullable|boolean',
        ];

        return $request->validate($rules);
    }
}
