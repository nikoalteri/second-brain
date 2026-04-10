<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('+10 days', '+30 days');
        $endDate = $this->faker->dateTimeBetween($startDate, '+45 days');

        return [
            'user_id' => User::factory(),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => TripStatus::PLANNING,
            'notes' => $this->faker->optional()->paragraph(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TripStatus::IN_PROGRESS,
            'start_date' => now()->subDays(5),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TripStatus::COMPLETED,
            'start_date' => now()->subDays(15),
            'end_date' => now()->subDays(5),
        ]);
    }
}
