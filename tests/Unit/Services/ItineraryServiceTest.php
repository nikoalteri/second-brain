<?php

namespace Tests\Unit\Services;

use App\Models\Activity;
use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\User;
use App\Services\ItineraryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class ItineraryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ItineraryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ItineraryService();
    }

    /** @test */
    public function create_itinerary_with_date_in_trip_range(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(20),
        ]);

        $itinerary = $this->service->createItinerary($user, $trip, [
            'date' => now()->addDays(15),
            'description' => 'Day 1 activities',
        ]);

        $this->assertInstanceOf(Itinerary::class, $itinerary);
        $this->assertEquals($trip->id, $itinerary->trip_id);
        $this->assertEquals($user->id, $itinerary->user_id);
    }

    /** @test */
    public function create_itinerary_validates_date_within_trip_range(): void
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->create([
            'user_id' => $user->id,
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(20),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('within trip date range');

        $this->service->createItinerary($user, $trip, [
            'date' => now()->addDays(25), // Outside trip range
        ]);
    }

    /** @test */
    public function add_activity_with_valid_times(): void
    {
        $itinerary = Itinerary::factory()->create();
        $startTime = now()->addDays(1)->setHour(9)->setMinute(0);
        $endTime = now()->addDays(1)->setHour(12)->setMinute(0);

        $activity = $this->service->addActivity($itinerary, [
            'title' => 'Museum Visit',
            'description' => 'Local museum tour',
            'start_time' => $startTime,
            'end_time' => $endTime,
            'type' => 'sightseeing',
        ]);

        $this->assertInstanceOf(Activity::class, $activity);
        $this->assertEquals('Museum Visit', $activity->title);
        $this->assertEquals($itinerary->id, $activity->itinerary_id);
    }

    /** @test */
    public function add_activity_validates_title_required(): void
    {
        $itinerary = Itinerary::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('title');

        $this->service->addActivity($itinerary, [
            'start_time' => now(),
            'end_time' => now()->addHours(1),
        ]);
    }

    /** @test */
    public function add_activity_validates_start_before_end(): void
    {
        $itinerary = Itinerary::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('start_time must be before end_time');

        $this->service->addActivity($itinerary, [
            'title' => 'Invalid Activity',
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(1),
        ]);
    }

    /** @test */
    public function detect_conflicts_returns_empty_for_non_overlapping(): void
    {
        $itinerary = Itinerary::factory()->create();
        Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'start_time' => now()->addDays(1)->setHour(9),
            'end_time' => now()->addDays(1)->setHour(11),
        ]);
        Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'start_time' => now()->addDays(1)->setHour(13),
            'end_time' => now()->addDays(1)->setHour(15),
        ]);

        $conflicts = $this->service->detectConflicts($itinerary);

        $this->assertEmpty($conflicts);
    }

    /** @test */
    public function detect_conflicts_identifies_overlapping_activities(): void
    {
        $itinerary = Itinerary::factory()->create();
        $start1 = now()->addDays(1)->setHour(9);
        $end1 = now()->addDays(1)->setHour(12);
        $start2 = now()->addDays(1)->setHour(11);
        $end2 = now()->addDays(1)->setHour(14);

        $activity1 = Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'title' => 'Morning Activity',
            'start_time' => $start1,
            'end_time' => $end1,
        ]);

        $activity2 = Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'title' => 'Afternoon Activity',
            'start_time' => $start2,
            'end_time' => $end2,
        ]);

        $conflicts = $this->service->detectConflicts($itinerary);

        $this->assertCount(1, $conflicts);
        $conflict = $conflicts[0];
        $this->assertEquals($activity1->id, $conflict['activity1_id']);
        $this->assertEquals($activity2->id, $conflict['activity2_id']);
        $this->assertNotNull($conflict['overlap_start']);
        $this->assertNotNull($conflict['overlap_end']);
    }

    /** @test */
    public function detect_conflicts_includes_overlap_times(): void
    {
        $itinerary = Itinerary::factory()->create();
        $start1 = now()->addDays(1)->setHour(9);
        $end1 = now()->addDays(1)->setHour(12);
        $start2 = now()->addDays(1)->setHour(11);
        $end2 = now()->addDays(1)->setHour(14);

        Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'start_time' => $start1,
            'end_time' => $end1,
        ]);

        Activity::factory()->create([
            'itinerary_id' => $itinerary->id,
            'start_time' => $start2,
            'end_time' => $end2,
        ]);

        $conflicts = $this->service->detectConflicts($itinerary);

        $this->assertCount(1, $conflicts);
        $conflict = $conflicts[0];

        // Overlap should be from 11:00 to 12:00
        $overlapStart = Carbon::parse($conflict['overlap_start']);
        $overlapEnd = Carbon::parse($conflict['overlap_end']);

        $this->assertTrue($overlapStart->equalTo(Carbon::parse($start2)));
        $this->assertTrue($overlapEnd->equalTo(Carbon::parse($end1)));
    }

    /** @test */
    public function update_activity_modifies_data(): void
    {
        $activity = Activity::factory()->create([
            'title' => 'Original Title',
            'start_time' => now()->addDays(1)->setHour(9),
            'end_time' => now()->addDays(1)->setHour(11),
        ]);

        $updated = $this->service->updateActivity($activity, [
            'title' => 'Updated Title',
        ]);

        $this->assertEquals('Updated Title', $updated->title);
    }

    /** @test */
    public function update_activity_validates_new_times(): void
    {
        $activity = Activity::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service->updateActivity($activity, [
            'start_time' => now()->addHours(2),
            'end_time' => now()->addHours(1),
        ]);
    }
}
