<?php

namespace Database\Factories;

use App\Models\TripBudget;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripBudgetFactory extends Factory
{
    protected $model = TripBudget::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'trip_id' => Trip::factory(),
            'initial_amount' => $this->faker->randomFloat(2, 1000, 10000),
            'currency' => $this->faker->currencyCode(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
