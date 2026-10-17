<?php

namespace App\Services;

use App\Models\CreditCardExpense;
use App\Models\Subscription;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Automatic-renewal postings of one subscription, merged across the account and card paths.
 *
 * Postings are the recorded charges generated from the configured price; they are not
 * bank-confirmed amounts.
 */
class SubscriptionRenewalHistory
{
    /**
     * The newest distinct renewal dates, newest first, each as a plain array:
     * date (Y-m-d), amount (positive decimal string), amount_minor (int), source
     * ('account'|'card'), frequency and currency (null on legacy rows) and ambiguous
     * (true when more than one posting claims the same date).
     *
     * @return array<int, array{date: string, amount: string, amount_minor: int, source: string, frequency: ?string, currency: ?string, ambiguous: bool}>
     */
    public function latest(Subscription $subscription, int $limit = 2): array
    {
        $ownerId = $subscription->user_id;

        // Global scopes follow the authenticated user; the owner is pinned explicitly instead so a
        // scheduler run (no auth) and another user's rows can never leak into the history.
        $accountPostings = Transaction::withoutGlobalScopes()
            ->where('user_id', $ownerId)
            ->where('subscription_id', $subscription->id)
            ->whereNotNull('subscription_renewal_date')
            ->orderByDesc('subscription_renewal_date')
            ->limit($limit + 1)
            ->get()
            ->map(fn (Transaction $row) => $this->posting($row, 'account'));

        $cardPostings = CreditCardExpense::query()
            ->where('subscription_id', $subscription->id)
            ->whereNotNull('subscription_renewal_date')
            ->whereHas('creditCard', fn ($query) => $query->withoutGlobalScopes()->where('user_id', $ownerId))
            ->orderByDesc('subscription_renewal_date')
            ->limit($limit + 1)
            ->get()
            ->map(fn (CreditCardExpense $row) => $this->posting($row, 'card'));

        /** @var Collection<string, Collection<int, array>> $byDate */
        $byDate = $accountPostings->concat($cardPostings)->groupBy('date');

        return $byDate
            ->sortKeysDesc()
            ->take($limit)
            ->map(function (Collection $rows) {
                $first = $rows->first();
                $first['ambiguous'] = $rows->count() > 1;

                return $first;
            })
            ->values()
            ->all();
    }

    private function posting(Transaction|CreditCardExpense $row, string $source): array
    {
        $amount = number_format(abs((float) $row->amount), 2, '.', '');

        return [
            'date' => CarbonImmutable::parse($row->subscription_renewal_date)->toDateString(),
            'amount' => $amount,
            'amount_minor' => (int) round(abs((float) $row->amount) * 100),
            'source' => $source,
            'frequency' => $row->renewal_frequency,
            'currency' => $row->renewal_currency,
            'ambiguous' => false,
        ];
    }
}
