<?php

namespace Database\Factories;

use App\Models\Itinerary;
use App\Models\Trip;
use App\Models\User;
use App\Models\Destination;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItineraryFactory extends Factory
{
    protected $model = Itinerary::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'trip_id' => Trip::factory(),
            'destination_id' => Destination::factory(),
            'date' => $this->faker->date(),
            'description' => $this->faker->optional()->sentence(),
        ];
    }
}
