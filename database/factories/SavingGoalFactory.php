<?php

namespace Database\Factories;

use App\Enums\SavingGoalStatus;
use App\Models\Account;
use App\Models\SavingGoal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SavingGoalFactory extends Factory
{
    protected $model = SavingGoal::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'name' => $this->faker->words(2, true) . ' fund',
            'target_amount' => 1000.00,
            'target_date' => now()->addMonths(6),
            'status' => SavingGoalStatus::ACTIVE,
            'notes' => null,
        ];
    }
}
