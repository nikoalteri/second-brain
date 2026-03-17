<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Account;
use App\Models\TransactionType;
use App\Models\TransactionCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    protected $model = Transaction::class;

    public function definition(): array
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $type = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['color' => '#ef4444', 'icon' => 'heroicon-o-arrow-down', 'is_income' => false]
        );
        $category = TransactionCategory::query()->firstOrCreate(
            ['user_id' => $user->id, 'name' => 'General', 'parent_id' => null],
            ['color' => null, 'icon' => null, 'is_active' => true]
        );

        return [
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $type->id,
            'transaction_category_id' => $category->id,
            'description' => $this->faker->sentence(),
            'amount' => $this->faker->randomFloat(2, -1000, 1000),
            'date' => $this->faker->date(),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }

    public function transfer(): static
    {
        return $this->state([
            'amount' => $this->faker->randomFloat(2, -1000, 1000),
            'transaction_type_id' => TransactionType::query()->firstOrCreate(
                ['name' => 'Transfer'],
                ['color' => '#6366f1', 'icon' => 'heroicon-o-arrows-right-left', 'is_income' => false]
            )->id,
        ]);
    }

    public function expense(): static
    {
        return $this->state([
            'amount' => $this->faker->randomFloat(2, -1000, -1),
            'transaction_type_id' => TransactionType::query()->firstOrCreate(
                ['name' => 'Expenses'],
                ['color' => '#ef4444', 'icon' => 'heroicon-o-arrow-down', 'is_income' => false]
            )->id,
        ]);
    }

    public function earning(): static
    {
        return $this->state([
            'amount' => $this->faker->randomFloat(2, 1, 1000),
            'transaction_type_id' => TransactionType::query()->firstOrCreate(
                ['name' => 'Earnings'],
                ['color' => '#22c55e', 'icon' => 'heroicon-o-arrow-up', 'is_income' => true]
            )->id,
        ]);
    }
}
