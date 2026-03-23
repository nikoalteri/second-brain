<?php

namespace Database\Factories;

use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditCardExpenseFactory extends Factory
{
    protected $model = CreditCardExpense::class;

    public function definition(): array
    {
        return [
            'credit_card_id' => CreditCard::factory(),
            'credit_card_cycle_id' => CreditCardCycle::factory(),
            'spent_at' => now(),
            'amount' => $this->faker->randomFloat(2, 10, 500),
            'description' => $this->faker->sentence(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
