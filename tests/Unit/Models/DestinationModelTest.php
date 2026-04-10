<?php

namespace Tests\Unit\Models;

use App\Models\Destination;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestinationModelTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function destination_creates_with_location_data()
    {
        $destination = Destination::factory()->create([
            'name' => 'Paris',
            'latitude' => 48.856613,
            'longitude' => 2.352222,
            'timezone' => 'Europe/Paris',
            'country' => 'FR',
        ]);

        $this->assertNotNull($destination->id);
        $this->assertEquals('Paris', $destination->name);
        $this->assertEquals(48.856613, (float) $destination->latitude);
        $this->assertEquals(2.352222, (float) $destination->longitude);
        $this->assertEquals('Europe/Paris', $destination->timezone);
        $this->assertEquals('FR', $destination->country);
    }

    /** @test */
    public function destination_belongs_to_user()
    {
        $user = User::factory()->create();
        $destination = Destination::factory()->for($user)->create();

        $this->assertEquals($user->id, $destination->user_id);
        $this->assertInstanceOf(User::class, $destination->user);
    }

    /** @test */
    public function destination_belongs_to_trip()
    {
        $trip = Trip::factory()->create();
        $destination = Destination::factory()->for($trip)->create();

        $this->assertEquals($trip->id, $destination->trip_id);
        $this->assertInstanceOf(Trip::class, $destination->trip);
    }

    /** @test */
    public function destination_latitude_casts_to_decimal()
    {
        $destination = Destination::factory()->create([
            'latitude' => 40.7128,
        ]);

        $this->assertIsNumeric($destination->latitude);
        // Decimal:8 precision will expand to 8 decimal places
        $this->assertStringStartsWith('40.7', (string) $destination->latitude);
    }

    /** @test */
    public function destination_longitude_casts_to_decimal()
    {
        $destination = Destination::factory()->create([
            'longitude' => -74.0060,
        ]);

        $this->assertIsNumeric($destination->longitude);
        // Decimal:8 precision will expand to 8 decimal places
        $this->assertStringStartsWith('-74', (string) $destination->longitude);
    }

    /** @test */
    public function destination_soft_deletes()
    {
        $destination = Destination::factory()->create();
        $destinationId = $destination->id;

        $destination->delete();

        $this->assertNull(Destination::find($destinationId));
        $this->assertNotNull(Destination::onlyTrashed()->find($destinationId));
    }

    /** @test */
    public function destination_timestamps_exist()
    {
        $destination = Destination::factory()->create();

        $this->assertNotNull($destination->created_at);
        $this->assertNotNull($destination->updated_at);
    }
}
