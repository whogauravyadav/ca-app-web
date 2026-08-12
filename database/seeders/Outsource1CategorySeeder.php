<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class Outsource1CategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => 'Daily CA', 'slug' => 'daily-ca', 'icon' => 'today', 'color' => '#92A8FE', 'sort_order' => 10],
            ['name' => 'Government Schemes', 'slug' => 'government-schemes', 'icon' => 'account_balance', 'color' => '#5C6BC0', 'sort_order' => 11],
            ['name' => 'Banking', 'slug' => 'banking', 'icon' => 'account_balance_wallet', 'color' => '#00897B', 'sort_order' => 12],
            ['name' => 'Reports & Indices', 'slug' => 'reports-indices', 'icon' => 'assessment', 'color' => '#6D4C41', 'sort_order' => 13],
            ['name' => 'Awards', 'slug' => 'awards', 'icon' => 'emoji_events', 'color' => '#F9A825', 'sort_order' => 14],
            ['name' => 'Places in News', 'slug' => 'places-in-news', 'icon' => 'place', 'color' => '#EF6C00', 'sort_order' => 15],
            ['name' => 'Important Days', 'slug' => 'important-days', 'icon' => 'event', 'color' => '#8E24AA', 'sort_order' => 16],
            ['name' => 'Sports', 'slug' => 'sports', 'icon' => 'sports_soccer', 'color' => '#43A047', 'sort_order' => 17],
            ['name' => 'Summits', 'slug' => 'summits', 'icon' => 'groups', 'color' => '#1565C0', 'sort_order' => 18],
            ['name' => 'Art & Culture', 'slug' => 'art-culture', 'icon' => 'palette', 'color' => '#AD1457', 'sort_order' => 19],
            ['name' => 'Ancient History', 'slug' => 'ancient-history', 'icon' => 'account_balance', 'color' => '#6D4C41', 'sort_order' => 20],
            ['name' => 'Medieval History', 'slug' => 'medieval-history', 'icon' => 'castle', 'color' => '#5D4037', 'sort_order' => 21],
            ['name' => 'Modern History', 'slug' => 'modern-history', 'icon' => 'history_edu', 'color' => '#4E342E', 'sort_order' => 22],
            ['name' => 'Indian Geography', 'slug' => 'indian-geography', 'icon' => 'map', 'color' => '#2E7D32', 'sort_order' => 23],
            ['name' => 'World Geography', 'slug' => 'world-geography', 'icon' => 'public', 'color' => '#0277BD', 'sort_order' => 24],
            ['name' => 'Polity', 'slug' => 'polity', 'icon' => 'gavel', 'color' => '#3949AB', 'sort_order' => 1],
            ['name' => 'Economy', 'slug' => 'economy', 'icon' => 'trending_up', 'color' => '#00897B', 'sort_order' => 2],
            ['name' => 'Science & Tech', 'slug' => 'science-tech', 'icon' => 'science', 'color' => '#7B1FA2', 'sort_order' => 3],
            ['name' => 'Environment', 'slug' => 'environment', 'icon' => 'eco', 'color' => '#43A047', 'sort_order' => 4],
            ['name' => 'International', 'slug' => 'international', 'icon' => 'public', 'color' => '#FB8C00', 'sort_order' => 5],
            ['name' => 'Defence', 'slug' => 'defence', 'icon' => 'security', 'color' => '#C62828', 'sort_order' => 6],
            ['name' => 'General Science', 'slug' => 'general-science', 'icon' => 'biotech', 'color' => '#00838F', 'sort_order' => 25],
            ['name' => 'Biology', 'slug' => 'biology', 'icon' => 'spa', 'color' => '#2E7D32', 'sort_order' => 26],
            ['name' => 'Physics', 'slug' => 'physics', 'icon' => 'bolt', 'color' => '#1565C0', 'sort_order' => 27],
            ['name' => 'Chemistry', 'slug' => 'chemistry', 'icon' => 'science', 'color' => '#6A1B9A', 'sort_order' => 28],
            ['name' => 'World History', 'slug' => 'world-history', 'icon' => 'public', 'color' => '#455A64', 'sort_order' => 29],
            ['name' => 'Books & Authors', 'slug' => 'books-authors', 'icon' => 'menu_book', 'color' => '#5D4037', 'sort_order' => 30],
        ];

        foreach ($rows as $row) {
            Category::updateOrCreate(
                ['slug' => $row['slug']],
                array_merge($row, ['is_active' => true])
            );
        }
    }
}
