<?php

namespace Tests\Unit\Services;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use App\Services\TravelService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TravelServiceTest extends TestCase
{
    use RefreshDatabase;

    private TravelService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TravelService();
    }

    /** @test */
    public function create_trip_with_valid_data_returns_trip(): void
    {
        $user = User::factory()->create();

        $trip = $this->service->createTrip($user, [
            'title' => 'Europe Adventure',
            'description' => 'Summer trip across Europe',
            'start_date' => now()->addDays(10),
            'end_date' => now()->addDays(20),
        ]);

        $this->assertInstanceOf(Trip::class, $trip);
        $this->assertEquals('Europe Adventure', $trip->title);
        $this->assertEquals(TripStatus::PLANNING, $trip->status);
        $this->assertEquals($user->id, $trip->user_id);
    }

    /** @test */
    public function create_trip_validates_start_date_before_end_date(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('start_date must be before end_date');

        $this->service->createTrip($user, [
            'title' => 'Invalid Trip',
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(10),
        ]);
    }

    /** @test */
    public function create_trip_throws_on_equal_dates(): void
    {
        $user = User::factory()->create();
        $sameDate = now()->addDays(10);

        $this->expectException(InvalidArgumentException::class);

        $this->service->createTrip($user, [
            'title' => 'Invalid Trip',
            'start_date' => $sameDate,
            'end_date' => $sameDate,
        ]);
    }

    /** @test */
    public function create_trip_requires_dates(): void
    {
        $user = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('required');

        $this->service->createTrip($user, [
            'title' => 'No Dates Trip',
        ]);
    }

    /** @test */
    public function update_trip_modifies_fields(): void
    {
        $trip = Trip::factory()->create([
            'title' => 'Original Title',
        ]);

        $updated = $this->service->updateTrip($trip, [
            'title' => 'Updated Title',
            'description' => 'New description',
        ]);

        $this->assertEquals('Updated Title', $updated->title);
        $this->assertEquals('New description', $updated->description);
        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'title' => 'Updated Title',
        ]);
    }

    /** @test */
    public function update_trip_validates_date_change(): void
    {
        $trip = Trip::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->service->updateTrip($trip, [
            'start_date' => now()->addDays(20),
            'end_date' => now()->addDays(10),
        ]);
    }

    /** @test */
    public function delete_trip_soft_deletes(): void
    {
        $trip = Trip::factory()->create();

        $result = $this->service->deleteTrip($trip);

        $this->assertTrue($result);
        $this->assertSoftDeleted('trips', ['id' => $trip->id]);
    }

    /** @test */
    public function create_trip_stores_in_database(): void
    {
        $user = User::factory()->create();
        $startDate = now()->addDays(10);
        $endDate = now()->addDays(20);

        $trip = $this->service->createTrip($user, [
            'title' => 'Database Test Trip',
            'description' => 'Test description',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $this->assertDatabaseHas('trips', [
            'id' => $trip->id,
            'user_id' => $user->id,
            'title' => 'Database Test Trip',
            'status' => 'planning',
        ]);
    }
}
