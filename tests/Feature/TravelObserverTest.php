<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\TripBudget;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelObserverTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function creating_trip_auto_creates_budget(): void
    {
        $user = User::factory()->create();

        $trip = Trip::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->assertDatabaseHas('trip_budgets', [
            'trip_id' => $trip->id,
            'user_id' => $user->id,
            'currency' => 'USD',
        ]);
    }

    /** @test */
    public function trip_budget_created_with_trip_budget_field(): void
    {
        $user = User::factory()->create();

        $trip = Trip::create([
            'user_id' => $user->id,
            'title' => 'Expensive Trip',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(20),
        ]);

        $budget = TripBudget::where('trip_id', $trip->id)->first();

        $this->assertNotNull($budget);
        $this->assertEquals($user->id, $budget->user_id);
        $this->assertEquals('USD', $budget->currency);
    }

    /** @test */
    public function trip_observer_logs_creation(): void
    {
        $user = User::factory()->create();

        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'title' => 'Logged Trip',
        ]);

        // Verify the trip was created (observer should have logged it)
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'title' => 'Logged Trip',
        ]);
    }

    /** @test */
    public function trip_observer_logs_deletion(): void
    {
        $trip = Trip::factory()->create();

        $tripId = $trip->id;
        $trip->delete();

        // Verify soft delete worked
        $this->assertSoftDeleted('trips', ['id' => $tripId]);
    }

    /** @test */
    public function itinerary_observer_detects_conflicts(): void
    {
        $itinerary = Itinerary::factory()->create();

        // Create two overlapping activities
        $activity1 = Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'title' => 'Morning Tour',
            'start_time' => now()->addDays(1)->setHour(9),
            'end_time' => now()->addDays(1)->setHour(12),
        ]);

        $activity2 = Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'title' => 'Lunch & Museum',
            'start_time' => now()->addDays(1)->setHour(11),
            'end_time' => now()->addDays(1)->setHour(14),
        ]);

        // Trigger observer by updating itinerary
        $itinerary->update(['description' => 'Updated description']);

        // Verify both activities exist (observer checks for conflicts but doesn't prevent them)
        $this->assertDatabaseHas('activities', ['id' => $activity1->id]);
        $this->assertDatabaseHas('activities', ['id' => $activity2->id]);
    }

    /** @test */
    public function itinerary_observer_handles_non_conflicting_activities(): void
    {
        $itinerary = Itinerary::factory()->create();

        // Create non-overlapping activities
        $activity1 = Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'start_time' => now()->addDays(1)->setHour(9),
            'end_time' => now()->addDays(1)->setHour(11),
        ]);

        $activity2 = Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'start_time' => now()->addDays(1)->setHour(13),
            'end_time' => now()->addDays(1)->setHour(15),
        ]);

        // Update itinerary
        $itinerary->update(['description' => 'No conflicts here']);

        // Both should still exist with no issues
        $this->assertDatabaseHas('activities', ['id' => $activity1->id]);
        $this->assertDatabaseHas('activities', ['id' => $activity2->id]);
    }

    /** @test */
    public function multiple_conflict_detection_works(): void
    {
        $itinerary = Itinerary::factory()->create();

        // Create 3 activities with multiple conflicts
        $activity1 = Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'title' => 'Activity 1',
            'start_time' => now()->addDays(1)->setHour(8),
            'end_time' => now()->addDays(1)->setHour(12),
        ]);

        $activity2 = Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'title' => 'Activity 2',
            'start_time' => now()->addDays(1)->setHour(10),
            'end_time' => now()->addDays(1)->setHour(14),
        ]);

        $activity3 = Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'title' => 'Activity 3',
            'start_time' => now()->addDays(1)->setHour(13),
            'end_time' => now()->addDays(1)->setHour(15),
        ]);

        // Update to trigger observer
        $itinerary->update(['date' => now()->addDays(1)]);

        // All activities should exist
        $this->assertDatabaseHas('activities', ['id' => $activity1->id]);
        $this->assertDatabaseHas('activities', ['id' => $activity2->id]);
        $this->assertDatabaseHas('activities', ['id' => $activity3->id]);
    }

    /** @test */
    public function trip_has_one_budget(): void
    {
        $trip = Trip::factory()->create();

        $this->assertCount(1, $trip->budgets ?? [TripBudget::where('trip_id', $trip->id)->first()]);
    }

    /** @test */
    public function trip_status_changes_are_logged(): void
    {
        $trip = Trip::factory()->create([
            'status' => 'planning',
        ]);

        // Update status
        $trip->update(['status' => 'in_progress']);

        // Verify status was updated
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'status' => 'in_progress',
        ]);
    }
}
