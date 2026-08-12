<?php

namespace App\Services\Outsource1;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Outsource1Client
{
    public function baseUrl(): string
    {
        return rtrim((string) config('outsource_1.base_url'), '/');
    }

    public function configured(): bool
    {
        return $this->baseUrl() !== '';
    }

    public function fetch(string $pathOrUrl): ?string
    {
        return $this->fetchWithStatus($pathOrUrl)['body'];
    }

    /**
     * @return array{status: int, body: ?string}
     */
    public function fetchWithStatus(string $pathOrUrl): array
    {
        $url = $this->absoluteUrl($pathOrUrl);
        $last = ['status' => 0, 'body' => null];

        foreach ($this->connectIps() as $ip) {
            $last = $this->request($url, $ip);
            if (is_string($last['body']) && $last['body'] !== '') {
                if (is_string($ip) && $ip !== '') {
                    Cache::put('outsource1.connect_ip', $ip, now()->addHours(6));
                }

                return $last;
            }
            if (in_array($last['status'], [401, 404, 410, 429, 500, 502, 503], true)) {
                return $last;
            }
        }

        return $last;
    }

    public function download(string $url): ?string
    {
        $got = $this->fetchWithStatus($url);

        return $got['body'];
    }

    /**
     * @return list<string>
     */
    public function listArticleUrls(int $maxPages): array
    {
        $urls = [];
        $maxPages = max(1, $maxPages);

        for ($page = 1; $page <= $maxPages; $page++) {
            $path = $page === 1 ? '/' : '/page/'.$page.'/';
            $html = $this->fetch($path);
            if ($html === null) {
                break;
            }

            $found = $this->extractArticleUrls($html);
            if ($found === []) {
                break;
            }
            foreach ($found as $url) {
                $urls[$url] = true;
            }
        }

        return array_keys($urls);
    }

    /**
     * @return list<string>
     */
    public function listDailyQuizUrls(Carbon $since, int $max): array
    {
        $urls = [];

        $indexHtml = $this->fetch((string) config('outsource_1.paths.daily_quiz_index', '/gk-current-affairs-quiz-questions-answers/'));
        if ($indexHtml) {
            foreach ($this->extractDailyQuizUrls($indexHtml) as $item) {
                if ($item['date']->gte($since->copy()->startOfDay())) {
                    $urls[$item['url']] = $item['date'];
                }
            }
        }

        foreach ([Carbon::today(), Carbon::yesterday()] as $day) {
            if ($day->gte($since->copy()->startOfDay())) {
                $path = '/daily-current-affairs-quiz-'.strtolower($day->format('F')).'-'.$day->day.'-'.$day->year.'/';
                $urls[$this->absoluteUrl($path)] = $day->copy();
            }
        }

        if (count($urls) <= 2) {
            $cursor = Carbon::today();
            $start = $since->copy()->startOfDay();
            while ($cursor->gte($start) && count($urls) < $max) {
                $path = '/daily-current-affairs-quiz-'.strtolower($cursor->format('F')).'-'.$cursor->day.'-'.$cursor->year.'/';
                $urls[$this->absoluteUrl($path)] = $cursor->copy();
                $cursor->subDay();
            }
        }

        uasort($urls, fn (Carbon $a, Carbon $b) => $b <=> $a);

        return array_slice(array_keys($urls), 0, $max);
    }

    /**
     * @return list<string>
     */
    public function monthHubUrls(Carbon $since): array
    {
        $urls = [];
        $cursor = Carbon::today()->startOfMonth();
        $start = $since->copy()->startOfMonth();
        $prefix = (string) config('outsource_1.paths.monthly_prefix', '/quizbase/current-affairs-quiz-');

        while ($cursor->gte($start)) {
            $urls[] = $this->absoluteUrl($prefix.strtolower($cursor->format('F')).'-'.$cursor->year);
            $cursor->subMonth();
        }

        return $urls;
    }

    /**
     * @return list<string>
     */
    public function hubPageUrls(string $path, int $maxPages): array
    {
        $urls = [];
        $maxPages = max(1, $maxPages);
        $base = $this->absoluteUrl($path);

        for ($page = 1; $page <= $maxPages; $page++) {
            $url = $page === 1 ? $base : rtrim($base, '/').'/page/'.$page.'/';
            $html = $this->fetch($url);
            if ($html === null) {
                break;
            }
            if ($page > 1 && ! str_contains($html, 'wp_quiz_question') && ! str_contains($html, 'sques_quiz')) {
                break;
            }
            $urls[] = $url;
        }

        return $urls;
    }

    /**
     * @return array{title: string, date: ?Carbon, category: ?string, body: string, image_url: ?string}|null
     */
    public function parseArticle(string $html, string $url): ?array
    {
        $title = $this->firstMatch($html, '/<h1[^>]*id=["\']list["\'][^>]*>(.*?)<\/h1>/is')
            ?? $this->firstMatch($html, '/<h1[^>]*class=["\'][^"\']*entry-title[^"\']*["\'][^>]*>(.*?)<\/h1>/is')
            ?? $this->firstMatch($html, '/<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)["\']/i')
            ?? $this->firstMatch($html, '/<title>(.*?)<\/title>/is');

        $title = $this->plain($title ?? '');
        $title = trim(preg_replace('/\s+\|\s+.*$/', '', $title) ?? $title);
        if ($title === '') {
            return null;
        }

        $dateRaw = $this->firstMatch($html, '/<[^>]*class=["\'][^"\']*post-date[^"\']*["\'][^>]*>(.*?)<\/[^>]+>/is')
            ?? $this->firstMatch($html, '/<time[^>]+datetime=["\']([^"\']+)["\']/i');
        $date = $this->parseDate($this->plain($dateRaw ?? ''));

        $category = $this->firstMatch($html, '/<[^>]*class=["\'][^"\']*post-categories[^"\']*["\'][^>]*>.*?<a[^>]*>(.*?)<\/a>/is');
        $category = $category ? $this->plain($category) : null;

        $image = $this->extractFeaturedImage($html);
        $body = $this->extractArticleBody($html);
        if ($body === '') {
            return null;
        }

        return [
            'title' => $title,
            'date' => $date,
            'category' => $category,
            'body' => $body,
            'image_url' => $image,
            'source_url' => $url,
        ];
    }

    /**
     * @return array{title: string, questions: list<array{question: string, options: list<string>, correct_index: int, explanation: ?string}>}
     */
    public function parseQuizPage(string $html, string $url): array
    {
        $title = $this->firstMatch($html, '/<h1[^>]*id=["\'](?:list|section)["\'][^>]*>(.*?)<\/h1>/is')
            ?? $this->firstMatch($html, '/<h1[^>]*>(.*?)<\/h1>/is')
            ?? 'Quiz';
        $title = $this->plain($title);

        $questions = $this->parseQuestionsLoose($html);

        return [
            'title' => $title,
            'source_url' => $url,
            'questions' => $questions,
        ];
    }

    /**
     * @return list<string>
     */
    public function extractLinkedQuizUrls(string $html): array
    {
        $urls = [];
        foreach ($this->extractDailyQuizUrls($html) as $item) {
            $urls[] = $item['url'];
        }

        return array_values(array_unique($urls));
    }

    public function absoluteUrl(string $pathOrUrl): string
    {
        if (Str::startsWith($pathOrUrl, ['http://', 'https://'])) {
            return $pathOrUrl;
        }

        return $this->baseUrl().'/'.ltrim($pathOrUrl, '/');
    }

    /**
     * @return list<?string>
     */
    private function connectIps(): array
    {
        $ips = [];
        $pinned = trim((string) config('outsource_1.connect_ip', ''));
        if ($pinned !== '') {
            $ips[] = $pinned;
        }
        $cached = Cache::get('outsource1.connect_ip');
        if (is_string($cached) && $cached !== '') {
            $ips[] = $cached;
        }
        foreach ($this->discoverEdgeIps() as $ip) {
            $ips[] = $ip;
        }
        $ips[] = null;

        $out = [];
        foreach ($ips as $ip) {
            $key = $ip ?? '';
            if (! array_key_exists($key, $out)) {
                $out[$key] = $ip;
            }
        }

        return array_values($out);
    }

    /**
     * @return list<string>
     */
    private function discoverEdgeIps(): array
    {
        $ips = [
            '162.159.137.232',
            '162.159.138.232',
            '162.159.136.232',
            '162.159.128.233',
        ];
        foreach ((array) config('outsource_1.edge_probe_hosts', []) as $host) {
            $records = @dns_get_record((string) $host, DNS_A) ?: [];
            foreach ($records as $row) {
                if (! empty($row['ip']) && filter_var($row['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ips[] = $row['ip'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * @return array{status: int, body: ?string}
     */
    private function request(string $url, ?string $connectIp): array
    {
        $timeout = (int) config('outsource_1.timeout', 30);
        $connectTimeout = (int) config('outsource_1.connect_timeout', 8);
        $host = parse_url($this->baseUrl(), PHP_URL_HOST) ?: parse_url($url, PHP_URL_HOST);
        $curl = [
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ];
        if ($connectIp && $host) {
            $curl[CURLOPT_RESOLVE] = [
                $host.':443:'.$connectIp,
                $host.':80:'.$connectIp,
            ];
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => config('outsource_1.user_agent'),
                'Accept' => 'text/html,application/xhtml+xml,image/*,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.9',
            ])->withOptions([
                'connect_timeout' => $connectTimeout,
                'timeout' => $timeout,
                'force_ip_resolve' => 'v4',
                'curl' => $curl,
            ])->get($url);

            $status = $response->status();
            if (! $response->successful()) {
                Log::info('Outsource1 fetch non-success', [
                    'url' => $url,
                    'status' => $status,
                    'connect_ip' => $connectIp,
                ]);

                return ['status' => $status, 'body' => null];
            }

            return ['status' => $status, 'body' => $response->body()];
        } catch (\Throwable $e) {
            Log::warning('Outsource1 fetch failed', [
                'url' => $url,
                'connect_ip' => $connectIp,
                'error' => $e->getMessage(),
            ]);

            return ['status' => 0, 'body' => null];
        }
    }

    /**
     * @return list<string>
     */
    private function extractArticleUrls(string $html): array
    {
        $found = [];
        preg_match_all('/<h[123][^>]*>\s*<a[^>]+href=["\']([^"\']+)["\'][^>]*>/i', $html, $m);
        foreach ($m[1] as $href) {
            $url = $this->normalizeUrl($href);
            if ($url && $this->isArticleUrl($url)) {
                $found[$url] = true;
            }
        }

        preg_match_all('/<a[^>]+rel=["\']bookmark["\'][^>]+href=["\']([^"\']+)["\']/i', $html, $m2);
        foreach ($m2[1] as $href) {
            $url = $this->normalizeUrl($href);
            if ($url && $this->isArticleUrl($url)) {
                $found[$url] = true;
            }
        }

        return array_keys($found);
    }

    /**
     * @return list<array{url: string, date: Carbon}>
     */
    private function extractDailyQuizUrls(string $html): array
    {
        $items = [];
        preg_match_all('/href=["\']([^"\']*daily-current-affairs-quiz-[^"\']+)["\']/i', $html, $m);
        foreach ($m[1] as $href) {
            $url = $this->normalizeUrl($href);
            if (! $url) {
                continue;
            }
            $date = $this->dateFromDailyQuizUrl($url);
            if ($date) {
                $items[] = ['url' => $url, 'date' => $date];
            }
        }

        return $items;
    }

    private function isArticleUrl(string $url): bool
    {
        $base = $this->baseUrl();
        if ($base === '' || ! Str::startsWith($url, $base)) {
            return false;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $path = '/'.trim($path, '/');
        if ($path === '/') {
            return false;
        }

        $skip = [
            '/quizbase', '/pdfs', '/category', '/tag', '/author', '/page',
            '/wp-content', '/wp-json', '/wp-login', '/cart', '/shop',
            '/gk-current-affairs-quiz', '/current-affairs-quiz',
            '/daily-current-affairs-quiz', '/about', '/contact', '/privacy',
            '/terms', '/login', '/register', '/my-account', '/hindi',
            '/cdn-cgi', '/gk-questions',
        ];
        foreach ($skip as $prefix) {
            if (Str::startsWith($path, $prefix)) {
                return false;
            }
        }
        if (in_array($path, ['/current-affairs', '/current-affairs/'], true)) {
            return false;
        }

        return (bool) preg_match('#^/[a-z0-9][a-z0-9%\-]+/?$#i', $path);
    }

    private function dateFromDailyQuizUrl(string $url): ?Carbon
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?: '');
        if (! preg_match('/daily-current-affairs-quiz-([a-z]+)-(\d{1,2})(?:-\d{1,2})?-(\d{4})/i', $path, $m)) {
            return null;
        }

        try {
            return Carbon::parse($m[1].' '.$m[2].' '.$m[3]);
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractFeaturedImage(string $html): ?string
    {
        $og = $this->firstMatch($html, '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\']/i')
            ?? $this->firstMatch($html, '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\']/i');
        if ($img = $this->firstMatch($html, '/<img[^>]*class=["\'][^"\']*post-featured-image[^"\']*["\'][^>]*>/is')) {
            $fromSrcset = $this->largestSrcset($img);
            if ($fromSrcset) {
                return $fromSrcset;
            }
            $src = $this->firstMatch($img, '/\bsrc=["\']([^"\']+)["\']/i');
            if ($src) {
                return html_entity_decode($src, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $og ? html_entity_decode($og, ENT_QUOTES | ENT_HTML5, 'UTF-8') : null;
    }

    private function largestSrcset(string $imgTag): ?string
    {
        $srcset = $this->firstMatch($imgTag, '/\bsrcset=["\']([^"\']+)["\']/i');
        if (! $srcset) {
            return null;
        }

        $bestUrl = null;
        $bestW = -1;
        foreach (explode(',', $srcset) as $part) {
            $part = trim($part);
            if (! preg_match('/(\S+)\s+(\d+)w/', $part, $m)) {
                continue;
            }
            $w = (int) $m[2];
            if ($w > $bestW) {
                $bestW = $w;
                $bestUrl = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $bestUrl;
    }

    private function extractArticleBody(string $html): string
    {
        $wrap = $this->firstMatch($html, '/<div[^>]*class=["\'][^"\']*content-wrap[^"\']*["\'][^>]*>(.*)$/is');
        $chunk = $wrap ?? $this->firstMatch($html, '/<div[^>]*class=["\'][^"\']*entry-content[^"\']*["\'][^>]*>(.*)$/is') ?? '';
        if ($chunk === '') {
            return '';
        }

        foreach (['related posts', 'you may also like', 'id="related"', 'class="related'] as $cut) {
            $pos = stripos($chunk, $cut);
            if ($pos !== false && $pos > 200) {
                $chunk = substr($chunk, 0, $pos);
            }
        }

        $chunk = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $chunk) ?? $chunk;
        $chunk = preg_replace('/<style\b[^>]*>.*?<\/style>/is', '', $chunk) ?? $chunk;
        $chunk = preg_replace('/<ins\b[^>]*>.*?<\/ins>/is', '', $chunk) ?? $chunk;
        $chunk = preg_replace('/<div[^>]*class=["\'][^"\']*(?:ad|adsbygoogle|applewrap)[^"\']*["\'][^>]*>.*?<\/div>/is', '', $chunk) ?? $chunk;

        // Close at a reasonable depth: keep first ~ content-wrap inner until next major aside
        $chunk = preg_replace('/<(aside|footer)\b.*$/is', '', $chunk) ?? $chunk;

        $allowed = '<p><br><h2><h3><h4><ul><ol><li><strong><b><em><i><a><blockquote><table><tr><td><th><thead><tbody>';
        $clean = trim(strip_tags($chunk, $allowed));
        $clean = preg_replace('/\n{3,}/', "\n\n", $clean) ?? $clean;

        return $clean;
    }

    /**
     * @return array{question: string, options: list<string>, correct_index: int, explanation: ?string}|null
     */
    private function parseQuestionBlock(string $chunk): ?array
    {
        $qHtml = $this->firstMatch($chunk, '/<div class="wp_quiz_question[^"]*">(.*?)<\/div>/is') ?? '';
        $question = $this->plain(preg_replace('/<span class="quesno">.*?<\/span>/is', '', $qHtml) ?? $qHtml);
        $question = trim(preg_replace('/^\d+\.\s*/', '', $question) ?? $question);
        if ($question === '') {
            return null;
        }

        $optHtml = $this->firstMatch($chunk, '/<div[^>]*class=["\'][^"\']*wp_quiz_question_options[^"\']*["\'][^>]*>(.*?)<\/div>/is') ?? '';
        $options = $this->parseOptions($optHtml);
        if (count($options) < 2) {
            return null;
        }

        $answerHtml = $this->firstMatch($chunk, '/<div class="ques_answer">(.*?)<\/div>/is') ?? '';
        $index = $this->correctIndex($this->plain($answerHtml), $options);
        if ($index === null) {
            return null;
        }

        $notes = $this->firstMatch($chunk, '/<div class="answer_hint">(.*?)<\/div>/is');
        $explanation = $notes ? trim(preg_replace('/^Notes:\s*/i', '', $this->plain($notes)) ?? '') : null;

        return [
            'question' => $question,
            'options' => array_values($options),
            'correct_index' => $index,
            'explanation' => $explanation ?: null,
        ];
    }

    /**
     * @return list<array{question: string, options: list<string>, correct_index: int, explanation: ?string}>
     */
    private function parseQuestionsLoose(string $html): array
    {
        $out = [];
        if (! preg_match_all('/<div class="wp_quiz_question[^"]*">(.*?)<\/div>\s*<div[^>]*class=["\'][^"\']*wp_quiz_question_options[^"\']*["\'][^>]*>(.*?)<\/div>(.*?)(?=<div class="wp_quiz_question|$)/is', $html, $m, PREG_SET_ORDER)) {
            return [];
        }

        foreach ($m as $row) {
            $parsed = $this->parseQuestionBlock($row[0]);
            if ($parsed) {
                $out[] = $parsed;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    private function parseOptions(string $html): array
    {
        $text = str_replace(['<br />', '<br/>', '<br>'], "\n", $html);
        $text = $this->plain($text);
        $options = [];
        if (preg_match_all('/\[([A-D])\]\s*(.+?)(?=\s*\[[A-D]\]|$)/s', $text, $m, PREG_SET_ORDER)) {
            foreach ($m as $row) {
                $options[] = trim($row[2]);
            }
        }

        return array_values(array_filter($options, fn ($o) => $o !== ''));
    }

    /**
     * @param  list<string>  $options
     */
    private function correctIndex(string $answerText, array $options): ?int
    {
        if (preg_match('/Correct Answer:\s*([A-D])\b/i', $answerText, $m)) {
            $idx = ord(strtoupper($m[1])) - 65;
            if (isset($options[$idx])) {
                return $idx;
            }
        }

        if (preg_match('/\[(.+)\]/', $answerText, $m)) {
            $needle = strtolower(trim($m[1]));
            foreach ($options as $i => $opt) {
                if (strtolower($opt) === $needle) {
                    return $i;
                }
            }
        }

        return null;
    }

    private function parseDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeUrl(string $href): ?string
    {
        $href = html_entity_decode(trim($href), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($href === '' || str_starts_with($href, '#')) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            $href = 'https:'.$href;
        } elseif (str_starts_with($href, '/')) {
            $href = $this->absoluteUrl($href);
        }
        if (! Str::startsWith($href, ['http://', 'https://'])) {
            return null;
        }

        $parts = parse_url($href);
        if (! $parts || empty($parts['host'])) {
            return null;
        }

        $path = $parts['path'] ?? '/';
        if (! str_ends_with($path, '/') && ! pathinfo($path, PATHINFO_EXTENSION)) {
            $path .= '/';
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].$path;
    }

    private function firstMatch(string $html, string $pattern): ?string
    {
        if (preg_match($pattern, $html, $m)) {
            return $m[1] ?? $m[0];
        }

        return null;
    }

    private function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
