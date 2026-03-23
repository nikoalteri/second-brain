<?php

namespace App\Services;

use App\Enums\SubscriptionFrequency;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class SubscriptionService
{
    /**
     * Calculate monthly cost from annual cost and frequency
     */
    public function calculateMonthlyCost(
        float $annualCost,
        SubscriptionFrequency $frequency
    ): float {
        $divisor = $frequency->getMonthlyDivisor();
        return round($annualCost / $divisor, 2);
    }

    /**
     * Calculate annual cost from monthly cost and frequency
     */
    public function calculateAnnualCost(
        float $monthlyCost,
        SubscriptionFrequency $frequency
    ): float {
        $multiplier = $frequency->getMonthlyDivisor();
        return round($monthlyCost * $multiplier, 2);
    }

    /**
     * Get total monthly cost for active subscriptions
     */
    public function getMonthlyTotal(?int $userId = null): float
    {
        $userId ??= Auth::id();
        
        return Subscription::where('user_id', $userId)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->get()
            ->sum(fn($sub) => $sub->frequency === SubscriptionFrequency::MONTHLY 
                ? $sub->monthly_cost
                : $this->calculateMonthlyCost($sub->annual_cost ?? 0, $sub->frequency)
            );
    }

    /**
     * Get upcoming renewals within N days
     */
    public function getUpcomingRenewals(
        int $days = 7,
        ?int $userId = null
    ): Collection {
        $userId ??= Auth::id();
        
        return Subscription::where('user_id', $userId)
            ->active()
            ->forRenewal($days)
            ->orderBy('next_renewal_date')
            ->get();
    }

    /**
     * Calculate next renewal date based on frequency
     */
    public function calculateNextRenewalDate(
        Subscription $sub,
        ?Carbon $from = null
    ): Carbon {
        $from ??= now();
        
        return match ($sub->frequency) {
            SubscriptionFrequency::MONTHLY => 
                $from->copy()->addMonth()->setDay($sub->day_of_month ?? 1),
            SubscriptionFrequency::ANNUAL => 
                $from->copy()->addYear()->setDay($sub->day_of_month ?? 1),
            SubscriptionFrequency::BIENNIAL => 
                $from->copy()->addYears(2)->setDay($sub->day_of_month ?? 1),
        };
    }

    /**
     * Process renewal: create transaction if enabled
     */
    public function processRenewal(Subscription $sub): void
    {
        if (!$sub->auto_create_transaction || !$sub->account_id) {
            return;
        }

        // Create expense transaction
        Transaction::create([
            'user_id' => $sub->user_id,
            'transaction_type_id' => TransactionType::where('name', 'Expense')->first()->id,
            'category_id' => $sub->category_id,
            'amount' => $sub->monthly_cost,
            'description' => "Subscription renewal: {$sub->name}",
            'date' => now(),
            'from_account_id' => $sub->account_id,
            'is_transfer' => false,
        ]);
    }
}
