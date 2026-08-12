<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\FetchLog;
use App\Services\Outsource1\Outsource1FetchService;
use Illuminate\Http\Request;

class Outsource1FetchController extends Controller
{
    public function fetch(Request $request, Outsource1FetchService $service)
    {
        @set_time_limit(300);

        $data = $request->validate([
            'mode' => 'nullable|in:articles,daily_quiz,monthly_quiz,topic_mcqs,gk_mcqs,all',
            'since' => 'nullable|date',
            'dry_run' => 'nullable|boolean',
            'publish' => 'nullable|boolean',
            'topic' => 'nullable|string|max:80',
        ]);

        $result = $service->run([
            'mode' => $data['mode'] ?? 'articles',
            'since' => $data['since'] ?? '2026-07-01',
            'dry_run' => (bool) ($data['dry_run'] ?? false),
            'publish' => (bool) ($data['publish'] ?? false),
            'topic' => $data['topic'] ?? null,
            'started_by' => $request->user()?->id,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'created' => $result['created'],
                'skipped' => $result['skipped'],
                'failed' => $result['failed'],
                'errors' => $result['errors'],
                'log' => $result['log'],
            ],
        ]);
    }

    public function logs()
    {
        $logs = FetchLog::query()
            ->where('source', 'outsource_1')
            ->latest()
            ->limit(30)
            ->get();

        return response()->json(['success' => true, 'data' => $logs]);
    }
}
