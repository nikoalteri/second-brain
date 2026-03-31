<?php

namespace Tests\Feature\Productivity;

use App\Models\Goal;
use App\Models\Habit;
use App\Models\JournalEntry;
use App\Models\Note;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductivityModuleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    // HABITS TESTS
    /** @test */
    public function user_can_create_habit()
    {
        $data = [
            'user_id' => $this->user->id,
            'name' => 'Morning Exercise',
            'description' => '30 mins cardio',
            'frequency' => 'daily',
            'start_date' => now()->toDateString(),
            'category' => 'health',
            'is_active' => true,
        ];

        $habit = Habit::create($data);

        $this->assertDatabaseHas('habits', [
            'user_id' => $this->user->id,
            'name' => 'Morning Exercise',
        ]);
        $this->assertEquals(0, $habit->current_streak);
    }

    /** @test */
    public function habits_are_scoped_to_user()
    {
        $otherUser = User::factory()->create();

        Habit::create([
            'user_id' => $this->user->id,
            'name' => 'Habit 1',
            'frequency' => 'daily',
            'start_date' => now()->toDateString(),
        ]);

        Habit::create([
            'user_id' => $otherUser->id,
            'name' => 'Habit 2',
            'frequency' => 'daily',
            'start_date' => now()->toDateString(),
        ]);

        $this->assertCount(1, $this->user->habits);
        $this->assertEquals('Habit 1', $this->user->habits->first()->name);
    }

    // GOALS TESTS
    /** @test */
    public function user_can_create_goal()
    {
        $data = [
            'user_id' => $this->user->id,
            'title' => 'Lose 5kg',
            'description' => 'Get to healthy weight',
            'category' => 'health',
            'start_date' => now()->toDateString(),
            'target_date' => now()->addMonths(3)->toDateString(),
            'status' => 'in_progress',
            'progress_percentage' => 25,
            'target_value' => 75.0,
            'current_value' => 80.0,
            'unit' => 'kg',
        ];

        $goal = Goal::create($data);

        $this->assertDatabaseHas('goals', [
            'user_id' => $this->user->id,
            'title' => 'Lose 5kg',
        ]);
        $this->assertEquals(25, $goal->progress_percentage);
    }

    /** @test */
    public function goal_status_values_are_valid()
    {
        $statuses = ['not_started', 'in_progress', 'completed', 'abandoned'];

        foreach ($statuses as $status) {
            $goal = Goal::create([
                'user_id' => $this->user->id,
                'title' => "Goal $status",
                'category' => 'personal',
                'start_date' => now()->toDateString(),
                'target_date' => now()->addMonths(1)->toDateString(),
                'status' => $status,
            ]);

            $this->assertEquals($status, $goal->status);
        }
    }

    // PROJECT TESTS
    /** @test */
    public function user_can_create_project()
    {
        $data = [
            'user_id' => $this->user->id,
            'name' => 'Website Redesign',
            'description' => 'Complete site redesign',
            'status' => 'in_progress',
            'priority' => 'high',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addMonths(2)->toDateString(),
            'progress_percentage' => 50,
            'color' => '#3b82f6',
        ];

        $project = Project::create($data);

        $this->assertDatabaseHas('projects', [
            'user_id' => $this->user->id,
            'name' => 'Website Redesign',
        ]);
        $this->assertEquals('high', $project->priority);
    }

    /** @test */
    public function project_can_be_marked_complete()
    {
        $project = Project::create([
            'user_id' => $this->user->id,
            'name' => 'Project',
            'status' => 'in_progress',
            'progress_percentage' => 100,
        ]);

        $project->update([
            'status' => 'completed',
            'completed_date' => now()->toDateString(),
        ]);

        $this->assertEquals('completed', $project->status);
        $this->assertNotNull($project->completed_date);
    }

    // JOURNAL ENTRY TESTS
    /** @test */
    public function user_can_create_journal_entry()
    {
        $entry = JournalEntry::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'content' => 'Today was productive. I completed all tasks...',
            'mood' => 'good',
            'emotion' => 'happy',
            'is_private' => true,
        ]);

        $this->assertNotNull($entry->id);
        $this->assertEquals('good', $entry->mood);
        $this->assertEquals('happy', $entry->emotion);
    }

    /** @test */
    public function journal_entries_scoped_to_user()
    {
        $otherUser = User::factory()->create();

        JournalEntry::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'content' => 'My thoughts',
        ]);

        JournalEntry::create([
            'user_id' => $otherUser->id,
            'date' => now()->toDateString(),
            'content' => 'Other thoughts',
        ]);

        $this->assertCount(1, $this->user->journalEntries);
    }

    /** @test */
    public function journal_mood_values_are_valid()
    {
        $moods = ['terrible', 'bad', 'neutral', 'good', 'excellent'];

        foreach ($moods as $mood) {
            $entry = JournalEntry::create([
                'user_id' => $this->user->id,
                'date' => now()->addDays(rand(1, 30))->toDateString(),
                'content' => 'Entry',
                'mood' => $mood,
            ]);

            $this->assertEquals($mood, $entry->mood);
        }
    }

    // NOTES TESTS
    /** @test */
    public function user_can_create_note()
    {
        $data = [
            'user_id' => $this->user->id,
            'title' => 'Quick Ideas',
            'content' => 'Some random ideas I had today...',
            'tags' => ['ideas', 'brainstorm'],
            'color' => 'yellow',
            'is_pinned' => false,
        ];

        $note = Note::create($data);

        $this->assertDatabaseHas('notes', [
            'user_id' => $this->user->id,
            'title' => 'Quick Ideas',
        ]);
        $this->assertEquals('yellow', $note->color);
    }

    /** @test */
    public function notes_can_be_pinned()
    {
        $note = Note::create([
            'user_id' => $this->user->id,
            'title' => 'Important Note',
            'content' => 'Remember this',
            'is_pinned' => true,
            'is_archived' => false,
        ]);

        $this->assertTrue($note->is_pinned);
        $this->assertFalse($note->is_archived);
    }

    /** @test */
    public function notes_can_be_archived()
    {
        $note = Note::create([
            'user_id' => $this->user->id,
            'title' => 'Old Note',
            'content' => 'Content',
            'is_archived' => true,
        ]);

        $this->assertTrue($note->is_archived);
    }

    // RELATIONSHIP TESTS
    /** @test */
    public function productivity_records_have_user_relationships()
    {
        Habit::create([
            'user_id' => $this->user->id,
            'name' => 'Habit',
            'frequency' => 'daily',
            'start_date' => now()->toDateString(),
        ]);

        Goal::create([
            'user_id' => $this->user->id,
            'title' => 'Goal',
            'category' => 'personal',
            'start_date' => now()->toDateString(),
            'target_date' => now()->addMonths(1)->toDateString(),
        ]);

        Project::create([
            'user_id' => $this->user->id,
            'name' => 'Project',
        ]);

        JournalEntry::create([
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
            'content' => 'Entry',
        ]);

        Note::create([
            'user_id' => $this->user->id,
            'title' => 'Note',
            'content' => 'Content',
        ]);

        $this->assertCount(1, $this->user->habits);
        $this->assertCount(1, $this->user->goals);
        $this->assertCount(1, $this->user->projects);
        $this->assertCount(1, $this->user->journalEntries);
        $this->assertCount(1, $this->user->notes);
    }

    /** @test */
    public function productivity_records_can_be_soft_deleted()
    {
        $habit = Habit::create([
            'user_id' => $this->user->id,
            'name' => 'Habit',
            'frequency' => 'daily',
            'start_date' => now()->toDateString(),
        ]);

        $habit->delete();

        $this->assertSoftDeleted('habits', ['id' => $habit->id]);
        $this->assertCount(0, $this->user->habits);
    }

    /** @test */
    public function note_color_values_are_valid()
    {
        $colors = ['yellow', 'blue', 'green', 'red', 'purple', 'pink'];

        foreach ($colors as $color) {
            $note = Note::create([
                'user_id' => $this->user->id,
                'title' => "Note $color",
                'content' => 'Content',
                'color' => $color,
            ]);

            $this->assertEquals($color, $note->color);
        }
    }

    /** @test */
    public function journal_emotion_values_are_valid()
    {
        $emotions = ['angry', 'sad', 'anxious', 'neutral', 'happy', 'excited'];

        foreach ($emotions as $emotion) {
            $entry = JournalEntry::create([
                'user_id' => $this->user->id,
                'date' => now()->addDays(rand(1, 30))->toDateString(),
                'content' => 'Entry',
                'emotion' => $emotion,
            ]);

            $this->assertEquals($emotion, $entry->emotion);
        }
    }
}
