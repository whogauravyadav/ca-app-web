<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\NotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class QuizAdminController extends Controller
{
    public function index()
    {
        $quizzes = Quiz::with('category:id,name')->withCount('questions')->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $quizzes->items(),
            'meta' => ['current_page' => $quizzes->currentPage(), 'last_page' => $quizzes->lastPage(), 'total' => $quizzes->total()],
        ]);
    }

    public function store(Request $request, NotificationDispatcher $notifications)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'article_id' => 'nullable|exists:articles,id',
            'time_limit_sec' => 'nullable|integer|min:30',
            'status' => 'nullable|in:draft,published',
            'questions' => 'nullable|array',
            'questions.*.question' => 'required_with:questions|string',
            'questions.*.options' => 'required_with:questions|array|min:2|max:6',
            'questions.*.correct_index' => 'required_with:questions|integer|min:0',
            'questions.*.explanation' => 'nullable|string',
        ]);

        $quiz = DB::transaction(function () use ($data) {
            $quiz = Quiz::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'category_id' => $data['category_id'] ?? null,
                'article_id' => $data['article_id'] ?? null,
                'time_limit_sec' => $data['time_limit_sec'] ?? null,
                'status' => $data['status'] ?? 'draft',
                'published_at' => ($data['status'] ?? '') === 'published' ? now() : null,
            ]);

            foreach ($data['questions'] ?? [] as $i => $q) {
                QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question' => $q['question'],
                    'options' => $q['options'],
                    'correct_index' => $q['correct_index'],
                    'explanation' => $q['explanation'] ?? null,
                    'sort_order' => $i,
                ]);
            }

            return $quiz->load('questions');
        });

        if ($quiz->status === 'published') {
            $notifications->notifyQuizPublished($quiz, $request->user()->id);
        }

        return response()->json(['success' => true, 'data' => $quiz], 201);
    }

    public function show(int $id)
    {
        return response()->json([
            'success' => true,
            'data' => Quiz::with(['questions', 'category'])->findOrFail($id),
        ]);
    }

    public function update(Request $request, int $id, NotificationDispatcher $notifications)
    {
        $quiz = Quiz::findOrFail($id);
        $wasPublished = $quiz->status === 'published';
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'article_id' => 'nullable|exists:articles,id',
            'time_limit_sec' => 'nullable|integer|min:30',
            'status' => 'nullable|in:draft,published',
            'questions' => 'nullable|array',
            'questions.*.question' => 'required_with:questions|string',
            'questions.*.options' => 'required_with:questions|array|min:2|max:6',
            'questions.*.correct_index' => 'required_with:questions|integer|min:0',
            'questions.*.explanation' => 'nullable|string',
        ]);

        DB::transaction(function () use ($quiz, $data) {
            $payload = collect($data)->except('questions')->all();
            if (($payload['status'] ?? null) === 'published' && ! $quiz->published_at) {
                $payload['published_at'] = now();
            }
            $quiz->update($payload);

            if (isset($data['questions'])) {
                $quiz->questions()->delete();
                foreach ($data['questions'] as $i => $q) {
                    QuizQuestion::create([
                        'quiz_id' => $quiz->id,
                        'question' => $q['question'],
                        'options' => $q['options'],
                        'correct_index' => $q['correct_index'],
                        'explanation' => $q['explanation'] ?? null,
                        'sort_order' => $i,
                    ]);
                }
            }
        });

        $quiz = $quiz->fresh('questions');
        if (! $wasPublished && $quiz->status === 'published') {
            $notifications->notifyQuizPublished($quiz, $request->user()->id);
        }

        return response()->json(['success' => true, 'data' => $quiz]);
    }

    public function destroy(int $id)
    {
        Quiz::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
