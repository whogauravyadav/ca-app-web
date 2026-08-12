<?php

return [
    'base_url' => rtrim(env('OUTSOURCE_1_BASE_URL', ''), '/'),
    'timeout' => (int) env('OUTSOURCE_1_TIMEOUT', 30),
    'connect_timeout' => (int) env('OUTSOURCE_1_CONNECT_TIMEOUT', 8),
    'max_article_pages' => (int) env('OUTSOURCE_1_MAX_ARTICLE_PAGES', 8),
    'max_articles' => (int) env('OUTSOURCE_1_MAX_ARTICLES', 40),
    'max_daily_quizzes' => (int) env('OUTSOURCE_1_MAX_DAILY_QUIZZES', 20),
    'max_quiz_pages' => (int) env('OUTSOURCE_1_MAX_QUIZ_PAGES', 3),
    'user_agent' => env(
        'OUTSOURCE_1_USER_AGENT',
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ),
    // Optional IPv4 pin when the origin anycast IPs are unreachable from this network.
    'connect_ip' => env('OUTSOURCE_1_CONNECT_IP', ''),
    'edge_probe_hosts' => [
        'cdnjs.cloudflare.com',
        'discord.com',
        'cloudflare.com',
    ],

    'paths' => [
        'home' => '/',
        'daily_quiz_index' => '/gk-current-affairs-quiz-questions-answers/',
        'monthly_prefix' => '/quizbase/current-affairs-quiz-',
    ],

    'topic_ca' => [
        'india-ca' => ['path' => '/quizbase/india-government-politics-current-affairs', 'category' => 'polity', 'title' => 'India Current Affairs MCQs'],
        'schemes' => ['path' => '/quizbase/government-schemes-current-affairs', 'category' => 'government-schemes', 'title' => 'Government Schemes MCQs'],
        'economy-banking' => ['path' => '/quizbase/business-economy-banking-current-affairs', 'category' => 'banking', 'title' => 'Business, Economy & Banking MCQs'],
        'defence' => ['path' => '/quizbase/defence-current-affairs', 'category' => 'defence', 'title' => 'Defence MCQs'],
        'reports' => ['path' => '/quizbase/reports-and-indices-current-affairs', 'category' => 'reports-indices', 'title' => 'Reports and Indices MCQs'],
        'environment' => ['path' => '/quizbase/environment-biodiversity-current-affairs', 'category' => 'environment', 'title' => 'Environment & Biodiversity MCQs'],
        'awards' => ['path' => '/quizbase/awards-honours-persons-in-news-current-affairs', 'category' => 'awards', 'title' => 'Awards, Honours & Persons MCQs'],
        'places' => ['path' => '/quizbase/places-in-news-current-affairs', 'category' => 'places-in-news', 'title' => 'Places in News MCQs'],
        'days' => ['path' => '/quizbase/important-days-and-events-current-affairs', 'category' => 'important-days', 'title' => 'Important Days and Events MCQs'],
        'sports' => ['path' => '/quizbase/sports-current-affairs', 'category' => 'sports', 'title' => 'Sports Current Affairs MCQs'],
        'science' => ['path' => '/quizbase/science-technology-current-affairs', 'category' => 'science-tech', 'title' => 'Science & Technology MCQs'],
        'summits' => ['path' => '/quizbase/summits-and-conferences-in-current-affairs', 'category' => 'summits', 'title' => 'Summits and Conferences MCQs'],
        'international' => ['path' => '/quizbase/international-current-affairs', 'category' => 'international', 'title' => 'International MCQs'],
        'art' => ['path' => '/quizbase/art-culture-current-affairs', 'category' => 'art-culture', 'title' => 'Art & Culture MCQs'],
    ],

    'gk_mcqs' => [
        'ancient' => ['path' => '/quizbase/ancient-indian-history-multiple-choice-questions', 'category' => 'ancient-history', 'title' => 'Ancient Indian History MCQs'],
        'medieval' => ['path' => '/quizbase/medieval-indian-history', 'category' => 'medieval-history', 'title' => 'Medieval Indian History MCQs'],
        'modern' => ['path' => '/quizbase/modern-indian-history-freedom-struggle', 'category' => 'modern-history', 'title' => 'Modern Indian History MCQs'],
        'indian-geo' => ['path' => '/quizbase/indian-geography-mcqs', 'category' => 'indian-geography', 'title' => 'Indian Geography MCQs'],
        'world-geo' => ['path' => '/quizbase/world-geography', 'category' => 'world-geography', 'title' => 'World Geography MCQs'],
        'polity' => ['path' => '/quizbase/indian-polity-constitution-mcqs', 'category' => 'polity', 'title' => 'Indian Polity & Constitution MCQs'],
        'env-gk' => ['path' => '/quizbase/environment-ecology-biodiversity-mcqs', 'category' => 'environment', 'title' => 'Environment & Biodiversity GK MCQs'],
        'culture' => ['path' => '/quizbase/indian-culture-general-studies-mcqs', 'category' => 'art-culture', 'title' => 'Indian Art & Culture MCQs'],
        'sports-gk' => ['path' => '/quizbase/sports-gk', 'category' => 'sports', 'title' => 'Sports GK MCQs'],
        'economy' => ['path' => '/quizbase/indian-economy-mcqs', 'category' => 'economy', 'title' => 'Indian Economy MCQs'],
        'gs' => ['path' => '/quizbase/general-science-for-competitive-examinations', 'category' => 'general-science', 'title' => 'General Science MCQs'],
        'biology' => ['path' => '/quizbase/general-science-biology-mcqs', 'category' => 'biology', 'title' => 'General Science - Biology MCQs'],
        'physics' => ['path' => '/quizbase/general-science-physics-mcqs', 'category' => 'physics', 'title' => 'General Science - Physics MCQs'],
        'chemistry' => ['path' => '/quizbase/general-science-chemistry', 'category' => 'chemistry', 'title' => 'General Science - Chemistry MCQs'],
        'world-history' => ['path' => '/quizbase/world-history-quiz-questions', 'category' => 'world-history', 'title' => 'World History MCQs'],
        'books' => ['path' => '/quizbase/books-and-authors-gk-questions', 'category' => 'books-authors', 'title' => 'Books & Authors MCQs'],
    ],
];
