<?php

namespace App\Console\Commands;

use App\Services\Outsource1\Outsource1FetchService;
use Illuminate\Console\Command;

class Outsource1FetchCommand extends Command
{
    protected $signature = 'outsource1:fetch
        {--mode=articles : articles|daily_quiz|monthly_quiz|topic_mcqs|gk_mcqs|all}
        {--since=2026-07-01 : Inclusive start date}
        {--dry-run : Parse only, do not write}
        {--publish : Save as published instead of draft}
        {--topic= : Optional hub key for topic_mcqs or gk_mcqs}';

    protected $description = 'Fetch articles and quizzes from Outsource 1';

    public function handle(Outsource1FetchService $service): int
    {
        $this->info('Fetching Outsource 1 (mode='.$this->option('mode').')…');

        try {
            $result = $service->run([
                'mode' => $this->option('mode'),
                'since' => $this->option('since'),
                'dry_run' => (bool) $this->option('dry-run'),
                'publish' => (bool) $this->option('publish'),
                'topic' => $this->option('topic') ?: null,
            ]);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Created: '.$result['created'].'  Skipped: '.$result['skipped'].'  Failed: '.$result['failed']);
        foreach (array_slice($result['errors'], 0, 15) as $err) {
            $this->warn(($err['url'] ?: '(run)').' — '.$err['reason']);
        }

        return self::SUCCESS;
    }
}
