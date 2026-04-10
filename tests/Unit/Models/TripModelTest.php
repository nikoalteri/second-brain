<?php

namespace Tests\Unit\Models;

use App\Models\Trip;
use App\Models\Destination;
use App\Models\Itinerary;
use App\Models\TripBudget;
use App\Models\TripParticipant;
use App\Models\User;
use App\Enums\TripStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function trip_creates_with_required_fields()
    {
        $trip = Trip::factory()->create([
            'title' => 'Summer Vacation',
            'status' => TripStatus::PLANNING,
        ]);

        $this->assertNotNull($trip->id);
        $this->assertEquals('Summer Vacation', $trip->title);
        $this->assertEquals(TripStatus::PLANNING, $trip->status);
    }

    /** @test */
    public function trip_has_one_user()
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->for($user)->create();

        $this->assertEquals($user->id, $trip->user_id);
        $this->assertInstanceOf(User::class, $trip->user);
    }

    /** @test */
    public function trip_has_many_destinations()
    {
        $trip = Trip::factory()->create();
        $destinations = Destination::factory(3)->for($trip)->create();

        $this->assertCount(3, $trip->destinations);
        $this->assertTrue($trip->destinations->pluck('id')->contains($destinations[0]->id));
    }

    /** @test */
    public function trip_has_many_itineraries()
    {
        $trip = Trip::factory()->create();
        Itinerary::factory(2)->for($trip)->create();

        $this->assertCount(2, $trip->itineraries);
    }

    /** @test */
    public function trip_has_one_budget()
    {
        $trip = Trip::factory()->create();
        TripBudget::factory()->for($trip)->create();

        // Refresh the trip to load the relationship
        $trip->refresh();

        $this->assertInstanceOf(TripBudget::class, $trip->budget);
        $this->assertNotNull($trip->budget->id);
    }

    /** @test */
    public function trip_has_many_participants()
    {
        $trip = Trip::factory()->create();
        TripParticipant::factory(4)->for($trip)->create();

        $this->assertCount(4, $trip->participants);
    }

    /** @test */
    public function trip_destination_count_attribute()
    {
        $trip = Trip::factory()->create();
        Destination::factory(5)->for($trip)->create();

        $this->assertEquals(5, $trip->destination_count);
    }

    /** @test */
    public function trip_soft_deletes()
    {
        $trip = Trip::factory()->create();
        $tripId = $trip->id;

        $trip->delete();

        $this->assertNull(Trip::find($tripId));
        $this->assertNotNull(Trip::onlyTrashed()->find($tripId));
    }

    /** @test */
    public function trip_date_casting()
    {
        $trip = Trip::factory()->create();

        $this->assertInstanceOf(\Carbon\Carbon::class, $trip->start_date);
        $this->assertInstanceOf(\Carbon\Carbon::class, $trip->end_date);
    }

    /** @test */
    public function trip_status_enum_casting()
    {
        $trip = Trip::factory()->create([
            'status' => TripStatus::PLANNING,
        ]);

        $this->assertInstanceOf(TripStatus::class, $trip->status);
        $this->assertEquals(TripStatus::PLANNING, $trip->status);
    }
}
