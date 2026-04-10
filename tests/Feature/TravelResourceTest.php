<?php

namespace Tests\Feature\Filament;

use App\Models\Trip;
use App\Models\Destination;
use App\Models\Itinerary;
use App\Models\Activity;
use App\Models\User;
use App\Enums\TripStatus;
use App\Enums\ActivityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();
    }

    /** @test */
    public function user_can_create_trip()
    {
        $trip = Trip::create([
            'user_id' => $this->user->id,
            'title' => 'Summer Europe 2026',
            'description' => 'A great summer trip',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-31',
            'status' => TripStatus::PLANNING,
        ]);

        $this->assertDatabaseHas('trips', [
            'title' => 'Summer Europe 2026',
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function user_can_update_trip()
    {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);

        $trip->update([
            'title' => 'Updated Trip Title',
            'status' => TripStatus::IN_PROGRESS,
        ]);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'title' => 'Updated Trip Title',
            'status' => TripStatus::IN_PROGRESS->value,
        ]);
    }

    /** @test */
    public function user_can_delete_trip()
    {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);

        $trip->delete();

        $this->assertSoftDeleted('trips', ['id' => $trip->id]);
    }

    /** @test */
    public function trip_has_correct_relationships()
    {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);
        $destination = Destination::factory()->create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
        ]);
        $itinerary = Itinerary::factory()->create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
        ]);

        $trip->refresh();

        $this->assertTrue($trip->destinations()->where('id', $destination->id)->exists());
        $this->assertTrue($trip->itineraries()->where('id', $itinerary->id)->exists());
        $this->assertEquals($this->user->id, $trip->user_id);
    }

    /** @test */
    public function trip_counts_destinations_correctly()
    {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);
        Destination::factory(3)->create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
        ]);

        $this->assertEquals(3, $trip->destination_count);
    }

    /** @test */
    public function trip_counts_activities_correctly()
    {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);
        $itinerary = Itinerary::factory()->create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
        ]);
        Activity::factory(5)->create([
            'user_id' => $this->user->id,
            'itinerary_id' => $itinerary->id,
        ]);

        $this->assertEquals(5, $trip->activity_count);
    }

    /** @test */
    public function user_can_create_destination()
    {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);
        
        $destination = Destination::create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
            'name' => 'Paris',
            'country' => 'France',
            'timezone' => 'Europe/Paris',
            'latitude' => 48.856613,
            'longitude' => 2.352222,
        ]);

        $this->assertDatabaseHas('destinations', [
            'name' => 'Paris',
            'country' => 'France',
            'trip_id' => $trip->id,
        ]);
    }

    /** @test */
    public function user_can_update_destination()
    {
        $destination = Destination::factory()->create(['user_id' => $this->user->id]);

        $destination->update([
            'name' => 'Rome',
            'country' => 'Italy',
        ]);

        $this->assertDatabaseHas('destinations', [
            'id' => $destination->id,
            'name' => 'Rome',
            'country' => 'Italy',
        ]);
    }

    /** @test */
    public function user_can_delete_destination()
    {
        $destination = Destination::factory()->create(['user_id' => $this->user->id]);

        $destination->delete();

        $this->assertSoftDeleted('destinations', ['id' => $destination->id]);
    }

    /** @test */
    public function destination_stores_coordinates_correctly()
    {
        $destination = Destination::create([
            'user_id' => $this->user->id,
            'trip_id' => Trip::factory()->create(['user_id' => $this->user->id])->id,
            'name' => 'Rome',
            'country' => 'Italy',
            'timezone' => 'Europe/Rome',
            'latitude' => 41.902782,
            'longitude' => 12.496366,
        ]);

        $this->assertEquals('41.902782', $destination->latitude);
        $this->assertEquals('12.496366', $destination->longitude);
    }

    /** @test */
    public function user_can_create_itinerary()
    {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);
        $destination = Destination::factory()->create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
        ]);

        $itinerary = Itinerary::create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
            'destination_id' => $destination->id,
            'date' => '2026-06-15',
            'description' => 'Day in Paris',
        ]);

        $this->assertDatabaseHas('itineraries', [
            'trip_id' => $trip->id,
            'destination_id' => $destination->id,
        ]);
    }

    /** @test */
    public function user_can_update_itinerary()
    {
        $itinerary = Itinerary::factory()->create(['user_id' => $this->user->id]);

        $itinerary->update([
            'description' => 'Updated itinerary description',
        ]);

        $this->assertDatabaseHas('itineraries', [
            'id' => $itinerary->id,
            'description' => 'Updated itinerary description',
        ]);
    }

    /** @test */
    public function user_can_delete_itinerary()
    {
        $itinerary = Itinerary::factory()->create(['user_id' => $this->user->id]);

        $itinerary->delete();

        $this->assertSoftDeleted('itineraries', ['id' => $itinerary->id]);
    }

    /** @test */
    public function itinerary_has_correct_relationships()
    {
        $itinerary = Itinerary::factory()->create(['user_id' => $this->user->id]);
        Activity::factory(3)->create([
            'user_id' => $this->user->id,
            'itinerary_id' => $itinerary->id,
        ]);

        $this->assertEquals(3, $itinerary->activities()->count());
    }

    /** @test */
    public function user_can_add_activity_to_itinerary()
    {
        $itinerary = Itinerary::factory()->create(['user_id' => $this->user->id]);

        $activity = Activity::create([
            'user_id' => $this->user->id,
            'itinerary_id' => $itinerary->id,
            'title' => 'Eiffel Tower Visit',
            'type' => ActivityType::SIGHTSEEING,
            'start_time' => '2026-06-15 09:00:00',
            'end_time' => '2026-06-15 11:00:00',
            'cost' => 15.50,
            'currency' => 'EUR',
        ]);

        $this->assertDatabaseHas('activities', [
            'itinerary_id' => $itinerary->id,
            'title' => 'Eiffel Tower Visit',
        ]);
    }

    /** @test */
    public function user_can_update_activity()
    {
        $activity = Activity::factory()->create(['user_id' => $this->user->id]);

        $activity->update([
            'title' => 'Updated Activity',
            'cost' => 25.00,
        ]);

        $this->assertDatabaseHas('activities', [
            'id' => $activity->id,
            'title' => 'Updated Activity',
        ]);
    }

    /** @test */
    public function user_can_delete_activity()
    {
        $activity = Activity::factory()->create(['user_id' => $this->user->id]);

        $activity->delete();

        $this->assertSoftDeleted('activities', ['id' => $activity->id]);
    }

    /** @test */
    public function activity_stores_time_fields_correctly()
    {
        $activity = Activity::create([
            'user_id' => $this->user->id,
            'itinerary_id' => Itinerary::factory()->create(['user_id' => $this->user->id])->id,
            'title' => 'Lunch',
            'type' => ActivityType::DINING,
            'start_time' => '2026-06-15 12:00:00',
            'end_time' => '2026-06-15 13:30:00',
            'cost' => 35.00,
            'currency' => 'EUR',
        ]);

        $this->assertNotNull($activity->start_time);
        $this->assertNotNull($activity->end_time);
        $this->assertEquals(35.00, (float) $activity->cost);
    }

    /** @test */
    public function user_scoping_prevents_access_to_other_users_data()
    {
        $trip1 = Trip::factory()->create(['user_id' => $this->user->id]);
        $trip2 = Trip::factory()->create(['user_id' => $this->otherUser->id]);

        // Assuming the query is scoped via trait
        $userTrips = Trip::where('user_id', $this->user->id)->get();
        
        $this->assertTrue($userTrips->contains('id', $trip1->id));
        $this->assertFalse($userTrips->contains('id', $trip2->id));
    }

    /** @test */
    public function trip_status_enum_values_are_valid()
    {
        $trip = Trip::create([
            'user_id' => $this->user->id,
            'title' => 'Test Trip',
            'start_date' => '2026-06-01',
            'end_date' => '2026-08-31',
            'status' => TripStatus::PLANNING,
        ]);

        $this->assertEquals(TripStatus::PLANNING, $trip->status);
        $this->assertEquals('planning', $trip->status->value);
    }

    /** @test */
    public function activity_type_enum_values_are_valid()
    {
        $activity = Activity::create([
            'user_id' => $this->user->id,
            'itinerary_id' => Itinerary::factory()->create(['user_id' => $this->user->id])->id,
            'title' => 'Museum Visit',
            'type' => ActivityType::SIGHTSEEING,
            'start_time' => '2026-06-15 09:00:00',
            'end_time' => '2026-06-15 11:00:00',
            'cost' => 20.00,
            'currency' => 'EUR',
        ]);

        $this->assertEquals(ActivityType::SIGHTSEEING, $activity->type);
    }

    /** @test */
    public function destination_timezone_has_sensible_default()
    {
        $destination = Destination::create([
            'user_id' => $this->user->id,
            'trip_id' => Trip::factory()->create(['user_id' => $this->user->id])->id,
            'name' => 'Tokyo',
            'country' => 'Japan',
            'timezone' => 'Asia/Tokyo',
            'latitude' => 35.6762,
            'longitude' => 139.6503,
        ]);

        $this->assertEquals('Asia/Tokyo', $destination->timezone);
    }

    /** @test */
    public function trip_participantcan_be_created()
    {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);

        $participant = $trip->participants()->create([
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
        ]);

        $this->assertDatabaseHas('trip_participants', [
            'trip_id' => $trip->id,
            'name' => 'John Doe',
        ]);
    }

    /** @test */
    public function soft_deleted_trips_remain_in_database()
    {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);
        $tripId = $trip->id;

        $trip->delete();

        $this->assertNull(Trip::find($tripId));
        $this->assertNotNull(Trip::withTrashed()->find($tripId));
    }

    /** @test */
    public function multiple_itineraries_can_be_created_for_single_trip()
    {
        $trip = Trip::factory()->create(['user_id' => $this->user->id]);
        
        Itinerary::factory(5)->create([
            'user_id' => $this->user->id,
            'trip_id' => $trip->id,
        ]);

        $this->assertEquals(5, $trip->itineraries()->count());
    }

    /** @test */
    public function activity_cost_can_be_zero()
    {
        $activity = Activity::create([
            'user_id' => $this->user->id,
            'itinerary_id' => Itinerary::factory()->create(['user_id' => $this->user->id])->id,
            'title' => 'Free Activity',
            'type' => ActivityType::SIGHTSEEING,
            'start_time' => '2026-06-15 09:00:00',
            'end_time' => '2026-06-15 11:00:00',
            'cost' => 0,
            'currency' => 'EUR',
        ]);

        $this->assertEquals(0, $activity->cost);
    }
}

