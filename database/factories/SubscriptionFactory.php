<?php

namespace Database\Factories;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $frequency = SubscriptionFrequency::MONTHLY;
        $monthly = 10.00;
        $annual = 120.00;
        
        return [
            'user_id' => \App\Models\User::factory(),
            'name' => $this->faker->word() . ' subscription',
            'frequency' => $frequency,
            'monthly_cost' => $monthly,
            'annual_cost' => $annual,
            'day_of_month' => 1,
            'next_renewal_date' => now()->addMonth(),
            'account_id' => null,
            'category_id' => null,
            'auto_create_transaction' => false,
            'status' => SubscriptionStatus::ACTIVE,
            'notes' => null,
        ];
    }
}
