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
        $user = User::factory()->create();
        
        return [
            'user_id' => $user->id,
            'trip_id' => Trip::factory()->for($user)->create()->id,
            'initial_amount' => $this->faker->randomFloat(2, 1000, 10000),
            'currency' => $this->faker->currencyCode(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
