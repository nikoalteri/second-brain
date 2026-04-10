<?php

namespace Database\Factories;

use App\Models\TripExpense;
use App\Models\TripParticipant;
use App\Models\TripBudget;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripExpenseFactory extends Factory
{
    protected $model = TripExpense::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'trip_participant_id' => TripParticipant::factory(),
            'trip_budget_id' => TripBudget::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => $this->faker->currencyCode(),
            'category' => $this->faker->optional()->word(),
            'description' => $this->faker->optional()->sentence(),
            'date' => $this->faker->date(),
        ];
    }
}
