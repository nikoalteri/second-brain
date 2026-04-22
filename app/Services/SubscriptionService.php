<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\CreditCardExpense;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\Transaction;
use App\Models\TransactionType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    /**
     * Calculate monthly-equivalent cost from a single renewal charge.
     */
    public function calculateMonthlyCost(
        float $billingAmount,
        SubscriptionFrequency $frequency
    ): float {
        $divisor = max(1, (int) $frequency->months_interval);

        return round($billingAmount / $divisor, 2);
    }

    /**
     * Calculate renewal charge from the monthly-equivalent amount.
     */
    public function calculateAnnualCost(
        float $monthlyCost,
        SubscriptionFrequency $frequency
    ): float {
        $multiplier = max(1, (int) $frequency->months_interval);

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
            ->sum(fn (Subscription $sub) => (float) $sub->monthly_cost);
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
            ->with(['frequencyOption', 'account', 'creditCard'])
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
        $sub->loadMissing('frequencyOption');

        $from ??= $sub->next_renewal_date ?? now();

        $candidate = $from->copy()->addMonthsNoOverflow(
            max(1, (int) ($sub->frequencyOption?->months_interval ?? 1))
        );

        return $candidate->day(min((int) ($sub->day_of_month ?? 1), $candidate->daysInMonth));
    }

    public function syncComputedFields(Subscription $subscription): void
    {
        $frequency = $subscription->relationLoaded('frequencyOption')
            ? $subscription->frequencyOption
            : SubscriptionFrequency::query()->find($subscription->subscription_frequency_id);

        if (! $frequency) {
            return;
        }

        if ($subscription->annual_cost !== null) {
            $subscription->monthly_cost = $this->calculateMonthlyCost((float) $subscription->annual_cost, $frequency);
        } elseif ($subscription->monthly_cost !== null) {
            $subscription->annual_cost = $this->calculateAnnualCost((float) $subscription->monthly_cost, $frequency);
        }

        $subscription->day_of_month ??= $subscription->next_renewal_date?->day ?? 1;
    }

    /**
     * Normalize validated API attributes into subscription storage fields.
     */
    public function prepareApiAttributes(array $validated, ?Subscription $subscription = null): array
    {
        $frequencyId = $validated['subscription_frequency_id']
            ?? $subscription?->subscription_frequency_id;

        $frequency = $frequencyId
            ? SubscriptionFrequency::query()->find($frequencyId)
            : null;

        $billingAmount = array_key_exists('billing_amount', $validated)
            ? $validated['billing_amount']
            : null;

        unset($validated['billing_amount']);

        if ($billingAmount !== null && $frequency) {
            $validated['annual_cost'] = round((float) $billingAmount, 2);
            $validated['monthly_cost'] = $this->calculateMonthlyCost((float) $billingAmount, $frequency);
        }

        if (array_key_exists('account_id', $validated) && ! empty($validated['account_id'])) {
            $validated['credit_card_id'] = null;
        }

        if (array_key_exists('credit_card_id', $validated) && ! empty($validated['credit_card_id'])) {
            $validated['account_id'] = null;
        }

        return $validated;
    }

    public function getBillingAmount(Subscription $subscription): float
    {
        $subscription->loadMissing('frequencyOption');

        $interval = max(1, (int) ($subscription->frequencyOption?->months_interval ?? 1));

        if ($interval === 1) {
            return round((float) ($subscription->monthly_cost ?? 0), 2);
        }

        return round((float) ($subscription->annual_cost ?? 0), 2);
    }

    public function hasPostingForRenewal(Subscription $subscription, CarbonInterface $renewalDate): bool
    {
        if ($subscription->credit_card_id) {
            return CreditCardExpense::query()
                ->where('subscription_id', $subscription->id)
                ->whereDate('subscription_renewal_date', $renewalDate->toDateString())
                ->exists();
        }

        return Transaction::withTrashed()
            ->where('subscription_id', $subscription->id)
            ->whereDate('subscription_renewal_date', $renewalDate->toDateString())
            ->exists();
    }

    public function syncDueRenewals(?CarbonInterface $throughDate = null): int
    {
        $throughDate ??= now()->endOfDay();

        $subscriptions = Subscription::query()
            ->with(['frequencyOption', 'account', 'creditCard'])
            ->active()
            ->where('auto_create_transaction', true)
            ->whereNotNull('next_renewal_date')
            ->whereDate('next_renewal_date', '<=', $throughDate->toDateString())
            ->get();

        $synced = 0;

        foreach ($subscriptions as $subscription) {
            while (
                $subscription->next_renewal_date
                && $subscription->next_renewal_date->copy()->endOfDay()->lessThanOrEqualTo($throughDate)
            ) {
                if (! $this->processRenewal($subscription, $subscription->next_renewal_date->copy())) {
                    break;
                }

                $synced++;
                $subscription->refresh()->load('frequencyOption');
            }
        }

        return $synced;
    }

    public function processRenewal(Subscription $subscription, ?CarbonInterface $renewalDate = null): bool
    {
        $subscription->loadMissing(['frequencyOption', 'creditCard', 'account']);

        if (! $subscription->auto_create_transaction) {
            return false;
        }

        $renewalDate ??= $subscription->next_renewal_date;

        if (! $renewalDate) {
            return false;
        }

        return DB::transaction(function () use ($subscription, $renewalDate) {
            if ($subscription->credit_card_id) {
                $this->upsertCreditCardExpense($subscription, $renewalDate);
            } elseif ($subscription->account_id) {
                $this->upsertTransaction($subscription, $renewalDate);
            } else {
                return false;
            }

            $subscription->updateQuietly([
                'next_renewal_date' => $this->calculateNextRenewalDate($subscription, Carbon::parse($renewalDate)),
            ]);

            return true;
        });
    }

    private function upsertTransaction(Subscription $subscription, CarbonInterface $renewalDate): void
    {
        $typeId = TransactionType::query()->firstOrCreate(
            ['name' => 'Expense'],
            ['is_income' => false]
        )->id;

        $payload = [
            'user_id' => $subscription->user_id,
            'account_id' => $subscription->account_id,
            'transaction_type_id' => $typeId,
            'transaction_category_id' => $subscription->category_id,
            'subscription_id' => $subscription->id,
            'amount' => -abs($this->getBillingAmount($subscription)),
            'date' => $renewalDate,
            'subscription_renewal_date' => $renewalDate,
            'competence_month' => $renewalDate->format('Y-m'),
            'description' => 'Subscription renewal - ' . $subscription->name,
            'notes' => $subscription->notes,
            'is_transfer' => false,
            'transfer_pair_id' => null,
            'transfer_direction' => null,
        ];

        $existing = Transaction::withTrashed()
            ->where('subscription_id', $subscription->id)
            ->whereDate('subscription_renewal_date', $renewalDate->toDateString())
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->fill($payload)->save();

            return;
        }

        Transaction::create($payload);
    }

    private function upsertCreditCardExpense(Subscription $subscription, CarbonInterface $renewalDate): void
    {
        $payload = [
            'credit_card_id' => $subscription->credit_card_id,
            'subscription_id' => $subscription->id,
            'spent_at' => $renewalDate,
            'posted_at' => $renewalDate,
            'subscription_renewal_date' => $renewalDate,
            'amount' => $this->getBillingAmount($subscription),
            'description' => 'Subscription renewal - ' . $subscription->name,
            'notes' => $subscription->notes,
        ];

        $existing = CreditCardExpense::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('subscription_renewal_date', $renewalDate->toDateString())
            ->first();

        if ($existing) {
            $existing->fill($payload)->save();

            return;
        }

        CreditCardExpense::create($payload);
    }
}
