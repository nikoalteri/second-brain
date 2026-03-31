<?php

namespace Database\Factories;

use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Enums\InterestCalculationMethod;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CreditCardFactory extends Factory
{
    protected $model = CreditCard::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'name' => $this->faker->word(),
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 5000.00,
            'fixed_payment' => 250.00,
            'interest_rate' => 12.00,
            'stamp_duty_amount' => 2.00,
            'statement_day' => 20,
            'due_day' => 25,
            'skip_weekends' => false,
            'current_balance' => 0.00,
            'status' => CreditCardStatus::ACTIVE,
            'start_date' => now(),
            'interest_calculation_method' => InterestCalculationMethod::DAILY_BALANCE,
        ];
    }

    public function charge(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => CreditCardType::CHARGE,
                'interest_rate' => 0.00,
                'fixed_payment' => 0.00,
            ];
        });
    }

    public function unlimited(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'credit_limit' => null,
            ];
        });
    }
}
