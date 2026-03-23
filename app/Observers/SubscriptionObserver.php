<?php

namespace App\Observers;

use App\Enums\SubscriptionFrequency;
use App\Models\Subscription;
use App\Services\SubscriptionService;

class SubscriptionObserver
{
    public function __construct(private SubscriptionService $service) {}

    /**
     * Calculate costs and next renewal date on creation
     */
    public function creating(Subscription $subscription): void
    {
        // Calculate missing cost based on frequency
        if (!$subscription->annual_cost && $subscription->monthly_cost) {
            $subscription->annual_cost = $this->service->calculateAnnualCost(
                $subscription->monthly_cost,
                $subscription->frequency
            );
        } elseif (!$subscription->monthly_cost && $subscription->annual_cost) {
            $subscription->monthly_cost = $this->service->calculateMonthlyCost(
                $subscription->annual_cost,
                $subscription->frequency
            );
        }

        // Set default day_of_month
        $subscription->day_of_month ??= 1;

        // Calculate next renewal date
        $subscription->next_renewal_date = $this->service->calculateNextRenewalDate($subscription);
    }

    /**
     * Recalculate on update if frequency or costs change
     */
    public function updating(Subscription $subscription): void
    {
        $this->creating($subscription);
    }
}
