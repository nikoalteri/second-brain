<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\Itinerary;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    public function definition(): array
    {
        $startTime = $this->faker->dateTime();
        $endTime = $this->faker->dateTimeBetween($startTime, '+3 hours');

        return [
            'user_id' => User::factory(),
            'itinerary_id' => Itinerary::factory(),
            'title' => $this->faker->sentence(2),
            'description' => $this->faker->optional()->sentence(),
            'type' => $this->faker->randomElement(ActivityType::cases()),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'cost' => $this->faker->optional()->randomFloat(2, 0, 500),
            'currency' => 'USD',
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
