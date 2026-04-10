<?php

namespace Database\Factories;

use App\Models\TripParticipant;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripParticipantFactory extends Factory
{
    protected $model = TripParticipant::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'trip_id' => Trip::factory(),
            'name' => $this->faker->name(),
            'email' => $this->faker->optional()->email(),
            'phone' => $this->faker->optional()->phoneNumber(),
        ];
    }
}
