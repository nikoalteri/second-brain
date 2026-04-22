<?php

namespace App\Observers;

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
        $this->service->syncComputedFields($subscription);
    }

    /**
     * Recalculate on update if frequency or costs change
     */
    public function updating(Subscription $subscription): void
    {
        $this->service->syncComputedFields($subscription);
    }
}
