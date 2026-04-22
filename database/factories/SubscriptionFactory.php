<?php

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        $monthly = 10.00;
        $defaultFrequencyId = SubscriptionFrequency::query()->firstOrCreate(
            ['slug' => 'monthly'],
            [
                'name' => 'Monthly',
                'months_interval' => 1,
                'sort_order' => 1,
                'is_active' => true,
            ]
        )->id;

        return [
            'user_id' => \App\Models\User::factory(),
            'name' => $this->faker->word() . ' subscription',
            'subscription_frequency_id' => $defaultFrequencyId,
            'monthly_cost' => $monthly,
            'annual_cost' => $monthly,
            'day_of_month' => 1,
            'next_renewal_date' => now()->addMonth(),
            'account_id' => null,
            'credit_card_id' => null,
            'category_id' => null,
            'auto_create_transaction' => false,
            'status' => SubscriptionStatus::ACTIVE,
            'notes' => null,
        ];
    }
}
