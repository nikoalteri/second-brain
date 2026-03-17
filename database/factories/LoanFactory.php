<?php

namespace Database\Factories;

use App\Models\Loan;
use App\Models\User;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoanFactory extends Factory
{
    protected $model = Loan::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'name' => $this->faker->word(),
            'total_amount' => $this->faker->randomFloat(2, 1000, 10000),
            'monthly_payment' => $this->faker->randomFloat(2, 100, 1000),
            'withdrawal_day' => $this->faker->numberBetween(1, 28),
            'skip_weekends' => true,
            'start_date' => $this->faker->date(),
            'end_date' => null,
            'total_installments' => $this->faker->numberBetween(12, 60),
            'paid_installments' => 0,
            'remaining_amount' => $this->faker->randomFloat(2, 1000, 10000),
            'status' => 'active',
        ];
    }

    public function completed(): static
    {
        return $this->state([
            'status' => 'completed',
        ]);
    }

    public function defaulted(): static
    {
        return $this->state([
            'status' => 'defaulted',
        ]);
    }
}
