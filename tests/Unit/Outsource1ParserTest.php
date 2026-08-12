<?php

namespace Tests\Unit;

use App\Services\Outsource1\Outsource1Client;
use Tests\TestCase;

class Outsource1ParserTest extends TestCase
{
    public function test_parses_article_title_date_body_and_image(): void
    {
        $html = <<<'HTML'
<html><head>
<meta property="og:image" content="https://cdn.example.com/hero.jpg" />
<title>Ignored | Site</title>
</head><body>
<h1 id="list">Parliament Passes Key Bill</h1>
<div class="post-date">August 12, 2026</div>
<div class="post-categories"><a href="/cat/polity">Polity</a></div>
<img class="post-featured-image wp-post-image" src="https://cdn.example.com/small.jpg"
  srcset="https://cdn.example.com/a.jpg 300w, https://cdn.example.com/hero-large.jpg 1200w" />
<div class="content-wrap">
<p>The bill was passed after a long debate on federal structure.</p>
<p>Exam takeaway: revise related articles of the Constitution.</p>
</div>
</body></html>
HTML;

        $client = new Outsource1Client;
        $parsed = $client->parseArticle($html, 'https://example.test/parliament-passes-key-bill/');

        $this->assertNotNull($parsed);
        $this->assertSame('Parliament Passes Key Bill', $parsed['title']);
        $this->assertSame('Polity', $parsed['category']);
        $this->assertSame('2026-08-12', $parsed['date']?->toDateString());
        $this->assertStringContainsString('federal structure', $parsed['body']);
        $this->assertSame('https://cdn.example.com/hero-large.jpg', $parsed['image_url']);
    }

    public function test_parses_mcq_options_answer_and_notes(): void
    {
        $html = <<<'HTML'
<h1 id="section">Daily Current Affairs Quiz</h1>
<div class="wp_quiz_question testclass"><span class="quesno">1. </span>What is the name of the mission?</div>
<div type="A" class="wp_quiz_question_options">[A] Mission Agni Varsha<br />[B] Mission Sudarshan Chakra<br />[C] Mission Vajra Kavach<br />[D] Mission Shakti</div>
<div class="ques_answer"><b>Correct Answer:</b> B [Mission Sudarshan Chakra]</div>
<div class="answer_hint"><b>Notes:</b><br />Announced by the Prime Minister.</div>
<div class="wp_quiz_question testclass"><span class="quesno">2. </span>Which country hosted the drill?</div>
<div type="A" class="wp_quiz_question_options">[A] Israel<br />[B] Iran<br />[C] Russia<br />[D] China</div>
<div class="ques_answer"><b>Correct Answer:</b> B [Iran]</div>
<div class="answer_hint"><b>Notes:</b><br />Held in the Gulf of Oman.</div>
HTML;

        $client = new Outsource1Client;
        $parsed = $client->parseQuizPage($html, 'https://example.test/daily-current-affairs-quiz-august-12-2026/');

        $this->assertSame('Daily Current Affairs Quiz', $parsed['title']);
        $this->assertCount(2, $parsed['questions']);
        $this->assertSame('What is the name of the mission?', $parsed['questions'][0]['question']);
        $this->assertSame('Mission Sudarshan Chakra', $parsed['questions'][0]['options'][1]);
        $this->assertSame(1, $parsed['questions'][0]['correct_index']);
        $this->assertStringContainsString('Prime Minister', $parsed['questions'][0]['explanation']);
        $this->assertSame(1, $parsed['questions'][1]['correct_index']);
    }
}
