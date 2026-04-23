<?php

namespace Database\Factories;

use App\Models\CategoryBudget;
use App\Models\TransactionCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CategoryBudget>
 */
class CategoryBudgetFactory extends Factory
{
    protected $model = CategoryBudget::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'transaction_category_id' => function (array $attributes) {
                return TransactionCategory::withoutGlobalScopes()->create([
                    'user_id' => $attributes['user_id'],
                    'name' => fake()->unique()->word(),
                    'is_active' => true,
                ])->id;
            },
            'period_start' => now()->startOfMonth()->toDateString(),
            'amount' => fake()->randomFloat(2, 50, 5000),
        ];
    }
}
