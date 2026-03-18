<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = Account::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->word(),
            'type' => 'bank',
            'balance' => $this->faker->randomFloat(2, 0, 10000),
            'opening_balance' => 0,
            'currency' => 'EUR',
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state([
            'is_active' => true,
        ]);
    }
}
