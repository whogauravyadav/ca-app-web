<?php

namespace App\Services\Outsource1;

use App\Models\Article;
use App\Models\FetchLog;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\User;
use App\Services\KtatvaStorageService;
use App\Services\NotificationDispatcher;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Outsource1FetchService
{
    public const MODES = ['articles', 'daily_quiz', 'monthly_quiz', 'topic_mcqs', 'gk_mcqs', 'all'];

    public function __construct(
        private Outsource1Client $client,
        private Outsource1Mapper $mapper,
        private KtatvaStorageService $storage,
        private NotificationDispatcher $notifications,
    ) {}

    /**
     * @param  array{mode?: string, since?: string, dry_run?: bool, publish?: bool, topic?: ?string, started_by?: ?int}  $options
     * @return array{log: FetchLog, created: int, skipped: int, failed: int, errors: list<array{url: string, reason: string}>}
     */
    public function run(array $options = []): array
    {
        @set_time_limit(300);

        $mode = $options['mode'] ?? 'articles';
        if (! in_array($mode, self::MODES, true)) {
            throw new \InvalidArgumentException('Invalid mode');
        }

        $since = Carbon::parse($options['since'] ?? '2026-07-01')->startOfDay();
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $publish = (bool) ($options['publish'] ?? false);
        $topic = $options['topic'] ?? null;
        $startedBy = $options['started_by'] ?? null;

        if (! $this->client->configured()) {
            throw new \RuntimeException('OUTSOURCE_1_BASE_URL is not configured');
        }

        $log = FetchLog::create([
            'source' => 'outsource_1',
            'mode' => $mode,
            'dry_run' => $dryRun,
            'publish' => $publish,
            'since' => $since->toDateString(),
            'status' => 'running',
            'started_by' => $startedBy,
        ]);

        $created = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];

        try {
            if (! $dryRun) {
                $this->mapper->ensureCategories();
            }

            $modes = $mode === 'all'
                ? ['articles', 'daily_quiz', 'monthly_quiz', 'topic_mcqs', 'gk_mcqs']
                : [$mode];

            foreach ($modes as $sub) {
                $result = match ($sub) {
                    'articles' => $this->fetchArticles($since, $dryRun, $publish, $startedBy),
                    'daily_quiz' => $this->fetchDailyQuizzes($since, $dryRun, $publish, $startedBy),
                    'monthly_quiz' => $this->fetchMonthlyQuizzes($since, $dryRun, $publish, $startedBy),
                    'topic_mcqs' => $this->fetchHubQuizzes('topic_ca', 'topic_ca', $dryRun, $publish, $startedBy, $topic),
                    'gk_mcqs' => $this->fetchHubQuizzes('gk_mcqs', 'gk', $dryRun, $publish, $startedBy, $topic),
                    default => ['created' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []],
                };
                $created += $result['created'];
                $skipped += $result['skipped'];
                $failed += $result['failed'];
                $errors = array_merge($errors, $result['errors']);
            }

            $log->update([
                'created_count' => $created,
                'skipped_count' => $skipped,
                'failed_count' => $failed,
                'errors' => $errors === [] ? null : array_slice($errors, 0, 80),
                'status' => 'ok',
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $errors[] = ['url' => '', 'reason' => $e->getMessage()];
            $log->update([
                'created_count' => $created,
                'skipped_count' => $skipped,
                'failed_count' => $failed + 1,
                'errors' => $errors,
                'status' => 'error',
                'finished_at' => now(),
            ]);
            throw $e;
        }

        return [
            'log' => $log->fresh(),
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{created: int, skipped: int, failed: int, errors: list<array{url: string, reason: string}>}
     */
    private function fetchArticles(Carbon $since, bool $dryRun, bool $publish, ?int $startedBy): array
    {
        $created = $skipped = $failed = 0;
        $errors = [];
        $max = (int) config('outsource_1.max_articles', 40);
        $pages = (int) config('outsource_1.max_article_pages', 8);
        $urls = $this->client->listArticleUrls($pages);
        if ($urls === []) {
            $home = $this->client->absoluteUrl('/');
            $probe = $this->client->fetchWithStatus('/');
            $failed = 1;
            $errors[] = [
                'url' => $home,
                'reason' => $probe['body'] === null
                    ? 'Could not reach Outsource 1 (HTTP '.$probe['status'].'). This network cannot connect to the source CDN; retry after the connectivity fix.'
                    : 'Reached Outsource 1 but found no article links on the listing pages.',
            ];

            return compact('created', 'skipped', 'failed', 'errors');
        }
        $processed = 0;
        $oldStreak = 0;

        foreach ($urls as $url) {
            if ($processed >= $max) {
                break;
            }
            if (Article::where('source_url', $url)->exists()) {
                $skipped++;
                $processed++;
                continue;
            }

            $got = $this->client->fetchWithStatus($url);
            if ($got['body'] === null) {
                if (in_array($got['status'], [404, 410], true)) {
                    $skipped++;
                } else {
                    $failed++;
                    $errors[] = ['url' => $url, 'reason' => 'HTTP fetch failed ('.$got['status'].')'];
                }
                continue;
            }

            $parsed = $this->client->parseArticle($got['body'], $url);
            if ($parsed === null) {
                $failed++;
                $errors[] = ['url' => $url, 'reason' => 'Could not parse article'];
                continue;
            }

            if ($parsed['date'] && $parsed['date']->lt($since)) {
                $skipped++;
                $oldStreak++;
                if ($oldStreak >= 8) {
                    break;
                }
                continue;
            }
            $oldStreak = 0;

            $processed++;

            if ($dryRun) {
                $created++;
                continue;
            }

            try {
                $this->storeArticle($parsed, $publish, $startedBy);
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['url' => $url, 'reason' => $e->getMessage()];
                Log::warning('Outsource1 article store failed', ['url' => $url, 'error' => $e->getMessage()]);
            }
        }

        return compact('created', 'skipped', 'failed', 'errors');
    }

    /**
     * @param  array{title: string, date: ?Carbon, category: ?string, body: string, image_url: ?string, source_url: string}  $parsed
     */
    private function storeArticle(array $parsed, bool $publish, ?int $startedBy): Article
    {
        $imageKey = null;
        if (! empty($parsed['image_url']) && $this->storage->configured()) {
            try {
                $bytes = $this->client->download($parsed['image_url']);
                if (is_string($bytes) && $bytes !== '') {
                    $ext = strtolower(pathinfo(parse_url($parsed['image_url'], PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg');
                    $ext = preg_replace('/[^a-z0-9]/', '', $ext) ?: 'jpg';
                    $tmp = tempnam(sys_get_temp_dir(), 'o1img_');
                    $named = $tmp.'.'.$ext;
                    rename($tmp, $named);
                    file_put_contents($named, $bytes);
                    $file = new UploadedFile($named, 'featured.'.$ext, null, null, true);
                    try {
                        $uploaded = $this->storage->upload($file, null, config('ktatva.prefix', 'articles'));
                        $imageKey = $uploaded['object_key'] ?? null;
                    } finally {
                        @unlink($named);
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Outsource1 image ingest failed', [
                    'url' => $parsed['source_url'],
                    'image' => $parsed['image_url'],
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $status = $publish ? 'published' : 'draft';
        $article = Article::create([
            'title' => Str::limit($parsed['title'], 250, ''),
            'slug' => Str::slug($parsed['title']).'-'.Str::random(5),
            'summary' => $this->mapper->summaryFromBody($parsed['body']),
            'body' => $parsed['body'],
            'category_id' => $this->mapper->categoryIdFor($parsed['category']),
            'author_id' => $startedBy ?: User::where('role', 'admin')->value('id'),
            'featured_image' => $imageKey,
            'read_time_min' => $this->mapper->readTimeMinutes($parsed['body']),
            'status' => $status,
            'published_at' => $publish ? ($parsed['date'] ?? now()) : null,
            'is_premium_early' => false,
            'source' => 'outsource_1',
            'source_url' => $parsed['source_url'],
        ]);

        if ($publish) {
            $this->notifications->notifyArticlePublished($article, $startedBy);
        }

        return $article;
    }

    /**
     * @return array{created: int, skipped: int, failed: int, errors: list<array{url: string, reason: string}>}
     */
    private function fetchDailyQuizzes(Carbon $since, bool $dryRun, bool $publish, ?int $startedBy): array
    {
        $max = (int) config('outsource_1.max_daily_quizzes', 20);
        $urls = $this->client->listDailyQuizUrls($since, $max);

        return $this->ingestQuizUrls($urls, 'daily', 'daily-ca', $dryRun, $publish, $startedBy, $since);
    }

    /**
     * @return array{created: int, skipped: int, failed: int, errors: list<array{url: string, reason: string}>}
     */
    private function fetchMonthlyQuizzes(Carbon $since, bool $dryRun, bool $publish, ?int $startedBy): array
    {
        $created = $skipped = $failed = 0;
        $errors = [];
        $maxPages = (int) config('outsource_1.max_quiz_pages', 3);

        foreach ($this->client->monthHubUrls($since) as $hubUrl) {
            $pageUrls = $this->client->hubPageUrls($hubUrl, $maxPages);
            if ($pageUrls === []) {
                $failed++;
                $errors[] = ['url' => $hubUrl, 'reason' => 'Monthly hub not found'];
                continue;
            }

            foreach ($pageUrls as $url) {
                $result = $this->ingestQuizUrls([$url], 'monthly', 'daily-ca', $dryRun, $publish, $startedBy, $since, true);
                $created += $result['created'];
                $skipped += $result['skipped'];
                $failed += $result['failed'];
                $errors = array_merge($errors, $result['errors']);
            }
        }

        return compact('created', 'skipped', 'failed', 'errors');
    }

    /**
     * @return array{created: int, skipped: int, failed: int, errors: list<array{url: string, reason: string}>}
     */
    private function fetchHubQuizzes(string $configKey, string $kind, bool $dryRun, bool $publish, ?int $startedBy, ?string $topic): array
    {
        $created = $skipped = $failed = 0;
        $errors = [];
        $hubs = config('outsource_1.'.$configKey, []);
        $maxPages = (int) config('outsource_1.max_quiz_pages', 3);

        foreach ($hubs as $key => $hub) {
            if ($topic && $topic !== $key) {
                continue;
            }
            $pageUrls = $this->client->hubPageUrls($hub['path'], $maxPages);
            if ($pageUrls === []) {
                $failed++;
                $errors[] = ['url' => $this->client->absoluteUrl($hub['path']), 'reason' => 'Hub fetch failed'];
                continue;
            }

            foreach ($pageUrls as $i => $url) {
                $title = $hub['title'] ?? $key;
                if ($i > 0) {
                    $title .= ' (page '.($i + 1).')';
                }
                $result = $this->ingestQuizUrls(
                    [$url],
                    $kind,
                    $hub['category'] ?? 'daily-ca',
                    $dryRun,
                    $publish,
                    $startedBy,
                    null,
                    false,
                    $title,
                );
                $created += $result['created'];
                $skipped += $result['skipped'];
                $failed += $result['failed'];
                $errors = array_merge($errors, $result['errors']);
            }
        }

        return compact('created', 'skipped', 'failed', 'errors');
    }

    /**
     * @param  list<string>  $urls
     * @return array{created: int, skipped: int, failed: int, errors: list<array{url: string, reason: string}>}
     */
    private function ingestQuizUrls(
        array $urls,
        string $kind,
        string $categorySlug,
        bool $dryRun,
        bool $publish,
        ?int $startedBy,
        ?Carbon $since = null,
        bool $followLinked = false,
        ?string $forcedTitle = null,
    ): array {
        $created = $skipped = $failed = 0;
        $errors = [];

        foreach ($urls as $url) {
            $got = $this->client->fetchWithStatus($url);
            if ($got['body'] === null) {
                if (in_array($got['status'], [404, 410], true)) {
                    $skipped++;
                } else {
                    $failed++;
                    $errors[] = ['url' => $url, 'reason' => 'HTTP fetch failed ('.$got['status'].')'];
                }
                continue;
            }

            $html = $got['body'];
            $parsed = $this->client->parseQuizPage($html, $url);
            $questions = array_slice($parsed['questions'], 0, 50);

            if ($questions === [] && $followLinked) {
                $linked = $this->client->extractLinkedQuizUrls($html);
                foreach (array_slice($linked, 0, 8) as $linkedUrl) {
                    $child = $this->ingestQuizUrls([$linkedUrl], $kind, $categorySlug, $dryRun, $publish, $startedBy, $since);
                    $created += $child['created'];
                    $skipped += $child['skipped'];
                    $failed += $child['failed'];
                    $errors = array_merge($errors, $child['errors']);
                }
                if ($questions === [] && $linked === []) {
                    $failed++;
                    $errors[] = ['url' => $url, 'reason' => 'No public questions/answers on page'];
                }
                continue;
            }

            if ($questions === []) {
                $failed++;
                $errors[] = ['url' => $url, 'reason' => 'Answers not in public HTML or parse failed'];
                continue;
            }

            $existing = Quiz::where('source_url', $url)->first();
            if ($existing) {
                if ($dryRun) {
                    $skipped++;
                    continue;
                }
                $added = $this->appendNewQuestions($existing, $questions);
                if ($added === 0) {
                    $skipped++;
                } else {
                    $created += $added;
                }
                continue;
            }

            if ($dryRun) {
                $created++;
                continue;
            }

            try {
                $this->storeQuiz(
                    $forcedTitle ?: $parsed['title'],
                    $url,
                    $kind,
                    $categorySlug,
                    $questions,
                    $publish,
                    $startedBy,
                );
                $created++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['url' => $url, 'reason' => $e->getMessage()];
            }
        }

        return compact('created', 'skipped', 'failed', 'errors');
    }

    /**
     * @param  list<array{question: string, options: list<string>, correct_index: int, explanation: ?string}>  $questions
     */
    private function storeQuiz(
        string $title,
        string $sourceUrl,
        string $kind,
        string $categorySlug,
        array $questions,
        bool $publish,
        ?int $startedBy,
    ): Quiz {
        return DB::transaction(function () use ($title, $sourceUrl, $kind, $categorySlug, $questions, $publish, $startedBy) {
            $quiz = Quiz::create([
                'title' => Str::limit($title, 250, ''),
                'description' => 'Imported from Outsource 1',
                'category_id' => $this->mapper->categoryIdFor($categorySlug, $categorySlug),
                'time_limit_sec' => min(1800, max(60, count($questions) * 45)),
                'status' => $publish ? 'published' : 'draft',
                'published_at' => $publish ? now() : null,
                'source' => 'outsource_1',
                'source_url' => $sourceUrl,
                'quiz_kind' => $kind,
            ]);

            foreach ($questions as $i => $q) {
                QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question' => $q['question'],
                    'options' => $q['options'],
                    'correct_index' => $q['correct_index'],
                    'explanation' => $q['explanation'] ?? null,
                    'sort_order' => $i,
                ]);
            }

            if ($publish) {
                $this->notifications->notifyQuizPublished($quiz, $startedBy);
            }

            return $quiz;
        });
    }

    /**
     * @param  list<array{question: string, options: list<string>, correct_index: int, explanation: ?string}>  $questions
     */
    private function appendNewQuestions(Quiz $quiz, array $questions): int
    {
        $existing = $quiz->questions()->pluck('question')->map(fn ($q) => mb_strtolower(trim($q)))->all();
        $sort = (int) $quiz->questions()->max('sort_order');
        $added = 0;

        foreach ($questions as $q) {
            $key = mb_strtolower(trim($q['question']));
            if (in_array($key, $existing, true)) {
                continue;
            }
            $sort++;
            QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question' => $q['question'],
                'options' => $q['options'],
                'correct_index' => $q['correct_index'],
                'explanation' => $q['explanation'] ?? null,
                'sort_order' => $sort,
            ]);
            $existing[] = $key;
            $added++;
        }

        return $added;
    }
}
