<?php

namespace Database\Seeders;

use App\Models\Goal;
use App\Models\Habit;
use App\Models\JournalEntry;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductivityDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create();

        // Habits - daily/weekly habits
        $habits = [
            ['name' => 'Morning Exercise', 'frequency' => 'daily', 'category' => 'health'],
            ['name' => 'Reading', 'frequency' => 'daily', 'category' => 'learning'],
            ['name' => 'Meditation', 'frequency' => 'daily', 'category' => 'health'],
            ['name' => 'Weekly Planning', 'frequency' => 'weekly', 'category' => 'productivity'],
            ['name' => 'Code Review', 'frequency' => 'weekly', 'category' => 'productivity'],
        ];

        foreach ($habits as $habit) {
            Habit::create([
                'user_id' => $user->id,
                'name' => $habit['name'],
                'frequency' => $habit['frequency'],
                'category' => $habit['category'],
                'start_date' => now()->subMonths(2)->toDateString(),
                'current_streak' => rand(1, 30),
                'longest_streak' => rand(30, 90),
                'total_completions' => rand(50, 200),
                'is_active' => true,
            ]);
        }

        // Goals
        $goals = [
            ['title' => 'Learn Golang', 'category' => 'career'],
            ['title' => 'Complete Side Project', 'category' => 'career'],
            ['title' => 'Read 12 Books', 'category' => 'personal'],
            ['title' => 'Save $10,000', 'category' => 'finance'],
            ['title' => 'Improve Fitness', 'category' => 'health'],
        ];

        foreach ($goals as $goal) {
            Goal::create([
                'user_id' => $user->id,
                'title' => $goal['title'],
                'category' => $goal['category'],
                'start_date' => now()->subMonths(1)->toDateString(),
                'target_date' => now()->addMonths(3)->toDateString(),
                'status' => ['not_started', 'in_progress'][rand(0, 1)],
                'progress_percentage' => rand(0, 80),
            ]);
        }

        // Projects
        $projects = [
            ['name' => 'Website Redesign', 'status' => 'in_progress', 'priority' => 'high'],
            ['name' => 'API Documentation', 'status' => 'in_progress', 'priority' => 'medium'],
            ['name' => 'Database Optimization', 'status' => 'planning', 'priority' => 'critical'],
            ['name' => 'Security Audit', 'status' => 'on_hold', 'priority' => 'high'],
        ];

        foreach ($projects as $proj) {
            Project::create([
                'user_id' => $user->id,
                'name' => $proj['name'],
                'status' => $proj['status'],
                'priority' => $proj['priority'],
                'start_date' => now()->subMonths(rand(1, 3))->toDateString(),
                'due_date' => now()->addMonths(rand(1, 4))->toDateString(),
                'progress_percentage' => rand(0, 100),
            ]);
        }

        // Journal Entries
        for ($i = 0; $i < 14; $i++) {
            JournalEntry::create([
                'user_id' => $user->id,
                'date' => now()->subDays($i)->toDateString(),
                'content' => 'Today reflection and thoughts...',
                'mood' => ['good', 'excellent', 'neutral'][rand(0, 2)],
                'is_private' => true,
            ]);
        }

        // Notes
        $notes = [
            ['title' => 'Ideas', 'color' => 'yellow'],
            ['title' => 'Bugs to Fix', 'color' => 'red'],
            ['title' => 'Research Topics', 'color' => 'blue'],
            ['title' => 'Wishlist', 'color' => 'green'],
        ];

        foreach ($notes as $note) {
            Note::create([
                'user_id' => $user->id,
                'title' => $note['title'],
                'content' => 'Notes about ' . $note['title'],
                'color' => $note['color'],
                'is_pinned' => rand(0, 1) === 1,
                'is_archived' => false,
            ]);
        }
    }
}
