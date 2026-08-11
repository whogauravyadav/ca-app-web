<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Bookmark;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptAnswer;
use App\Models\ReadingProgress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    public function index(Request $request)
    {
        $query = Quiz::query()->published()->with('category:id,name,slug')->withCount('questions');

        if ($request->filled('category')) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $request->category));
        }

        $quizzes = $query->latest('published_at')->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $quizzes->items(),
            'meta' => [
                'current_page' => $quizzes->currentPage(),
                'last_page' => $quizzes->lastPage(),
                'total' => $quizzes->total(),
            ],
        ]);
    }

    public function show(int $id)
    {
        $quiz = Quiz::query()
            ->published()
            ->with(['questions' => fn ($q) => $q->select('id', 'quiz_id', 'question', 'options', 'sort_order'), 'category:id,name,slug'])
            ->findOrFail($id);

        // Hide correct answers until submit
        $questions = $quiz->questions->map(fn ($q) => [
            'id' => $q->id,
            'question' => $q->question,
            'options' => $q->options,
            'sort_order' => $q->sort_order,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'description' => $quiz->description,
                'time_limit_sec' => $quiz->time_limit_sec,
                'category' => $quiz->category,
                'questions' => $questions,
            ],
        ]);
    }

    public function submit(Request $request, int $id)
    {
        $data = $request->validate([
            'answers' => 'required|array',
            'answers.*.question_id' => 'required|integer',
            'answers.*.selected_index' => 'nullable|integer|min:0|max:10',
        ]);

        $quiz = Quiz::query()->published()->with('questions')->findOrFail($id);
        $user = $request->user();

        $score = 0;
        $results = [];

        $attempt = DB::transaction(function () use ($quiz, $user, $data, &$score, &$results) {
            $attempt = QuizAttempt::create([
                'user_id' => $user->id,
                'quiz_id' => $quiz->id,
                'score' => 0,
                'total' => $quiz->questions->count(),
                'completed_at' => now(),
            ]);

            $byId = $quiz->questions->keyBy('id');

            foreach ($data['answers'] as $ans) {
                $question = $byId->get($ans['question_id']);
                if (! $question) {
                    continue;
                }
                $selected = $ans['selected_index'] ?? null;
                $correct = $selected !== null && (int) $selected === (int) $question->correct_index;
                if ($correct) {
                    $score++;
                }

                QuizAttemptAnswer::create([
                    'quiz_attempt_id' => $attempt->id,
                    'quiz_question_id' => $question->id,
                    'selected_index' => $selected,
                    'is_correct' => $correct,
                ]);

                $results[] = [
                    'question_id' => $question->id,
                    'selected_index' => $selected,
                    'correct_index' => $question->correct_index,
                    'is_correct' => $correct,
                    'explanation' => $question->explanation,
                ];
            }

            $attempt->update(['score' => $score]);

            return $attempt;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'attempt_id' => $attempt->id,
                'score' => $score,
                'total' => $attempt->total,
                'percent' => $attempt->total > 0 ? round(($score / $attempt->total) * 100) : 0,
                'results' => $results,
            ],
        ]);
    }
}
