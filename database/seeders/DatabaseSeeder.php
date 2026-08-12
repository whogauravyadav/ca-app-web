<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'admin@currentaffairs.app'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'role' => 'admin',
                'subscription_status' => 'active',
                'subscription_expires_at' => now()->addYear(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'student@currentaffairs.app'],
            [
                'name' => 'Demo Student',
                'password' => 'password',
                'role' => 'student',
                'subscription_status' => 'free',
            ]
        );

        $this->call(Outsource1CategorySeeder::class);

        $categories = [
            ['name' => 'Polity', 'slug' => 'polity', 'icon' => 'gavel', 'color' => '#3949AB', 'sort_order' => 1],
            ['name' => 'Economy', 'slug' => 'economy', 'icon' => 'trending_up', 'color' => '#00897B', 'sort_order' => 2],
            ['name' => 'Science & Tech', 'slug' => 'science-tech', 'icon' => 'science', 'color' => '#7B1FA2', 'sort_order' => 3],
            ['name' => 'Environment', 'slug' => 'environment', 'icon' => 'eco', 'color' => '#43A047', 'sort_order' => 4],
            ['name' => 'International', 'slug' => 'international', 'icon' => 'public', 'color' => '#FB8C00', 'sort_order' => 5],
            ['name' => 'Defence', 'slug' => 'defence', 'icon' => 'security', 'color' => '#C62828', 'sort_order' => 6],
        ];

        $catModels = [];
        foreach ($categories as $c) {
            $catModels[$c['slug']] = Category::updateOrCreate(['slug' => $c['slug']], $c);
        }

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'monthly'],
            [
                'name' => 'Monthly Premium',
                'price_inr' => 99,
                'duration_days' => 30,
                'features' => ['Ad-free reading', 'Offline bookmarks', 'Early daily CA'],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        SubscriptionPlan::updateOrCreate(
            ['slug' => 'yearly'],
            [
                'name' => 'Yearly Premium',
                'price_inr' => 799,
                'duration_days' => 365,
                'features' => ['Ad-free reading', 'Offline bookmarks', 'Early daily CA', 'Best value'],
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        $samples = [
            ['polity', 'Parliament Passes Key Constitutional Amendment', 'A landmark bill cleared both houses after detailed debate on federal structure and citizen rights.'],
            ['economy', 'RBI Holds Repo Rate Amid Inflation Cool-off', 'The Monetary Policy Committee kept the policy rate unchanged, signalling a data-dependent stance.'],
            ['science-tech', 'ISRO Successfully Tests Next-Gen Launch Vehicle Stage', 'The test validates propulsion systems critical for upcoming Gaganyaan missions.'],
            ['environment', 'India Expands Protected Marine Areas Along West Coast', 'New sanctuaries aim to conserve coral reefs and support coastal livelihoods.'],
            ['international', 'India Hosts Global Climate Finance Dialogue', 'Leaders discussed adaptation funding for developing economies and technology transfer.'],
            ['defence', 'Indigenous Missile System Completes Night Trial', 'The successful trial strengthens deterrence and export potential under Make in India.'],
            ['polity', 'Supreme Court Clarifies Free Speech Limits Online', 'Judgment balances platform liability with individual expression rights.'],
            ['economy', 'GST Collections Hit Record Monthly High', 'Strong compliance and festive demand pushed collections above expectations.'],
            ['science-tech', 'National AI Mission Announces Startup Grants', 'Selected teams will build solutions for agriculture, health, and education.'],
            ['environment', 'Monsoon Forecast Signals Above-Normal Rainfall', 'IMD outlook is positive for kharif crops across major agricultural belts.'],
        ];

        foreach ($samples as $i => [$slug, $title, $summary]) {
            $cat = $catModels[$slug];
            Article::updateOrCreate(
                ['slug' => Str::slug($title)],
                [
                    'title' => $title,
                    'summary' => $summary,
                    'body' => '<p>'.$summary.'</p><p>This article covers the background, key stakeholders, and exam-relevant takeaways for UPSC and state PSC aspirants.</p><h3>Key points</h3><ul><li>Context and timeline</li><li>Institutions involved</li><li>Impact on policy and citizens</li><li>Previous year linkage</li></ul><p>Revise related static topics from your standard textbooks alongside this update.</p>',
                    'category_id' => $cat->id,
                    'author_id' => $admin->id,
                    'read_time_min' => 4,
                    'status' => 'published',
                    'published_at' => now()->subHours(10 - $i),
                    'is_premium_early' => $i === 0,
                ]
            );
        }

        $quiz = Quiz::updateOrCreate(
            ['title' => 'Daily Current Affairs Quiz'],
            [
                'description' => '5 questions from today\'s headlines',
                'category_id' => $catModels['polity']->id,
                'time_limit_sec' => 300,
                'status' => 'published',
                'published_at' => now(),
            ]
        );

        if ($quiz->questions()->count() === 0) {
            $questions = [
                [
                    'question' => 'Which body typically decides India\'s repo rate?',
                    'options' => ['NITI Aayog', 'Monetary Policy Committee', 'Finance Commission', 'SEBI'],
                    'correct_index' => 1,
                    'explanation' => 'The RBI\'s Monetary Policy Committee sets the policy repo rate.',
                ],
                [
                    'question' => 'Gaganyaan is associated with which organisation?',
                    'options' => ['DRDO', 'ISRO', 'HAL', 'BARC'],
                    'correct_index' => 1,
                    'explanation' => 'Gaganyaan is ISRO\'s human spaceflight programme.',
                ],
                [
                    'question' => 'GST is primarily a tax on?',
                    'options' => ['Income', 'Wealth', 'Supply of goods and services', 'Imports only'],
                    'correct_index' => 2,
                    'explanation' => 'GST is a destination-based tax on supply of goods and services.',
                ],
                [
                    'question' => 'Marine protected areas mainly help conserve?',
                    'options' => ['Coal reserves', 'Coral reefs and marine biodiversity', 'Desert flora', 'Urban forests'],
                    'correct_index' => 1,
                    'explanation' => 'They protect marine ecosystems including coral reefs.',
                ],
                [
                    'question' => 'Make in India emphasises?',
                    'options' => ['Import substitution only', 'Domestic manufacturing and exports', 'Only services', 'Agriculture subsidies'],
                    'correct_index' => 1,
                    'explanation' => 'It promotes manufacturing, innovation, and export competitiveness.',
                ],
            ];

            foreach ($questions as $i => $q) {
                QuizQuestion::create([
                    'quiz_id' => $quiz->id,
                    'question' => $q['question'],
                    'options' => $q['options'],
                    'correct_index' => $q['correct_index'],
                    'explanation' => $q['explanation'],
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
