<?php

namespace Database\Factories;

use App\Models\SubscriptionFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SubscriptionFrequencyFactory extends Factory
{
    protected $model = SubscriptionFrequency::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->word()) . ' plan';

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'months_interval' => 1,
            'sort_order' => 1,
            'is_active' => true,
        ];
    }

    public function monthly(): static
    {
        return $this->state(fn () => [
            'name' => 'Monthly',
            'slug' => 'monthly',
            'months_interval' => 1,
            'sort_order' => 1,
        ]);
    }

    public function annual(): static
    {
        return $this->state(fn () => [
            'name' => 'Annual',
            'slug' => 'annual',
            'months_interval' => 12,
            'sort_order' => 2,
        ]);
    }

    public function biennial(): static
    {
        return $this->state(fn () => [
            'name' => 'Every 2 Years',
            'slug' => 'biennial',
            'months_interval' => 24,
            'sort_order' => 3,
        ]);
    }
}
