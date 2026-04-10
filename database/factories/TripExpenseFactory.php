<?php

namespace Database\Factories;

use App\Models\TripExpense;
use App\Models\TripParticipant;
use App\Models\TripBudget;
use App\Models\User;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripExpenseFactory extends Factory
{
    protected $model = TripExpense::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        $trip = Trip::factory()->for($user)->create();
        
        return [
            'user_id' => $user->id,
            'trip_participant_id' => TripParticipant::factory()->for($user)->for($trip)->create()->id,
            'trip_budget_id' => TripBudget::factory()->for($user)->for($trip)->create()->id,
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'currency' => $this->faker->currencyCode(),
            'category' => $this->faker->optional()->word(),
            'description' => $this->faker->optional()->sentence(),
            'date' => $this->faker->date(),
        ];
    }
}
