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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
    public function getMonthlyTotal(int $userId): float
    {
        // withoutUserScope() so $userId is the sole authority: without it, HasUserScoping's
        // global scope ANDs in the currently authenticated user, silently returning 0 whenever
        // the caller (a superadmin, a scheduled job) asks about a user other than themselves.
        return Subscription::withoutUserScope()
            ->where('user_id', $userId)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->get()
            ->sum(fn (Subscription $sub) => (float) $sub->monthly_cost);
    }

    /**
     * Get upcoming renewals within N days
     */
    public function getUpcomingRenewals(
        int $days,
        int $userId
    ): Collection {
        // See getMonthlyTotal() above for why withoutUserScope() is required here too.
        return Subscription::withoutUserScope()
            ->where('user_id', $userId)
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
            ->withoutUserScope()
            ->with(['frequencyOption', 'account', 'creditCard'])
            ->active()
            ->where('auto_create_transaction', true)
            ->whereNotNull('next_renewal_date')
            ->whereDate('next_renewal_date', '<=', $throughDate->toDateString())
            ->get();

        $synced = 0;

        // One subscription's exception must not stop every other subscription's renewal from
        // being processed on the same nightly run.
        foreach ($subscriptions as $subscription) {
            try {
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
            } catch (\Throwable $e) {
                Log::error("subscriptions:sync-renewals failed for subscription {$subscription->id}", ['exception' => $e]);
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
            // Serialise overlapping runs for the same subscription: the second one waits here and
            // then finds the renewal already posted instead of racing the lookup below.
            $locked = Subscription::withoutGlobalScopes()->whereKey($subscription->getKey())->lockForUpdate()->first();

            if ($locked === null) {
                return false;
            }

            // The instance was loaded before the lock: a run that held it may have switched the
            // billing source meanwhile, so decide the path from the committed row.
            $subscription->refresh()->loadMissing(['frequencyOption', 'creditCard', 'account']);

            if ($subscription->credit_card_id) {
                $created = $this->upsertCreditCardExpense($subscription, $renewalDate);
            } elseif ($subscription->account_id) {
                $created = $this->upsertTransaction($subscription, $renewalDate);
            } else {
                return false;
            }

            // A publisher failure propagates and rolls back the charge and the date advance together.
            if ($created) {
                app(SubscriptionPriceHikeDetector::class)->detect($subscription, Carbon::parse($renewalDate));
            }

            // A run that already advanced past this date must not be rewound by a repeat of it.
            if ($subscription->next_renewal_date === null || $subscription->next_renewal_date->lte(Carbon::parse($renewalDate))) {
                $subscription->updateQuietly([
                    'next_renewal_date' => $this->calculateNextRenewalDate($subscription, Carbon::parse($renewalDate)),
                ]);
            }

            return true;
        });
    }

    /**
     * @return bool true when a new charge was inserted, false when the renewal was already posted
     */
    private function upsertTransaction(Subscription $subscription, CarbonInterface $renewalDate): bool
    {
        // The renewal may already be posted on the card path (source switched between runs).
        if ($this->hasCardPosting($subscription, $renewalDate)) {
            return false;
        }

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
            'renewal_frequency' => $subscription->frequencyOption?->slug,
            'renewal_currency' => $subscription->account?->currency,
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
            $this->keepExistingTransaction($existing);

            return false;
        }

        try {
            Transaction::create($payload);

            return true;
        } catch (UniqueConstraintViolationException) {
            // The unique index caught a concurrent run that posted this renewal after our lookup.
            $this->keepExistingTransaction(
                Transaction::withTrashed()
                    ->where('subscription_id', $subscription->id)
                    ->whereDate('subscription_renewal_date', $renewalDate->toDateString())
                    ->firstOrFail()
            );

            return false;
        }
    }

    private function hasCardPosting(Subscription $subscription, CarbonInterface $renewalDate): bool
    {
        return CreditCardExpense::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('subscription_renewal_date', $renewalDate->toDateString())
            ->exists();
    }

    private function hasAccountPosting(Subscription $subscription, CarbonInterface $renewalDate): bool
    {
        return Transaction::withTrashed()
            ->where('subscription_id', $subscription->id)
            ->whereDate('subscription_renewal_date', $renewalDate->toDateString())
            ->exists();
    }

    // A retry never rewrites the posted amount or snapshots: the first charge is the history.
    private function keepExistingTransaction(Transaction $existing): void
    {
        if ($existing->trashed()) {
            $existing->restore();
        }
    }

    /**
     * @return bool true when a new charge was inserted, false when the renewal was already posted
     */
    private function upsertCreditCardExpense(Subscription $subscription, CarbonInterface $renewalDate): bool
    {
        // The renewal may already be posted on the account path (source switched between runs).
        if ($this->hasAccountPosting($subscription, $renewalDate)) {
            return false;
        }

        $payload = [
            'credit_card_id' => $subscription->credit_card_id,
            'subscription_id' => $subscription->id,
            'spent_at' => $renewalDate,
            'posted_at' => $renewalDate,
            'subscription_renewal_date' => $renewalDate,
            'renewal_frequency' => $subscription->frequencyOption?->slug,
            // Cards carry no currency of their own; the settlement account's currency is the evidence.
            'renewal_currency' => $subscription->creditCard?->account?->currency,
            'amount' => $this->getBillingAmount($subscription),
            'description' => 'Subscription renewal - ' . $subscription->name,
            'notes' => $subscription->notes,
        ];

        $existing = CreditCardExpense::query()
            ->where('subscription_id', $subscription->id)
            ->whereDate('subscription_renewal_date', $renewalDate->toDateString())
            ->first();

        if ($existing) {
            return false;
        }

        try {
            CreditCardExpense::create($payload);

            return true;
        } catch (UniqueConstraintViolationException) {
            // See upsertTransaction(): a concurrent run posted this renewal after our lookup.
            return false;
        }
    }
}
