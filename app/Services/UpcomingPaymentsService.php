<?php

namespace App\Services;

use App\Models\CreditCardPayment;
use App\Models\LoanPayment;
use App\Models\Subscription;
use App\Models\User;

class UpcomingPaymentsService
{
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    /**
     * Always scoped to the given user, including a superadmin: both callers (the dashboard and
     * the chatbot) pass the currently authenticated user and expect only their own upcoming
     * items back, not the whole system's. An admin-wide view would be a different, explicit
     * method, not an implicit role check inside "for this one user".
     *
     * @return array<int, array<string, mixed>>
     */
    public function forUser(User $user, int $days = 3): array
    {
        $today = now()->startOfDay();
        $until = now()->addDays($days)->endOfDay();

        $loanPayments = LoanPayment::query()
            ->with(['loan', 'postingTransaction'])
            ->whereBetween('due_date', [$today, $until])
            ->where('status', '!=', 'paid')
            ->whereHas('loan', fn ($loanQuery) => $loanQuery->where('user_id', $user->id))
            ->get()
            ->map(fn (LoanPayment $payment) => [
                'id' => 'loan-' . $payment->id,
                'payment_id' => $payment->id,
                'type' => 'loan',
                'description' => $payment->loan?->name ?? 'Loan installment',
                'amount' => (float) $payment->amount,
                'due_date' => $payment->due_date?->toDateString(),
                'status' => (string) $payment->status->value,
                'days_until_due' => $payment->due_date ? $today->diffInDays($payment->due_date, false) : null,
                'transaction_posted' => (bool) $payment->postingTransaction,
            ]);

        $creditCardPayments = CreditCardPayment::query()
            ->with(['creditCard', 'postingTransaction'])
            ->whereBetween('due_date', [$today, $until])
            ->where('status', '!=', 'paid')
            ->whereHas('creditCard', fn ($cardQuery) => $cardQuery->where('user_id', $user->id))
            ->get()
            ->map(fn (CreditCardPayment $payment) => [
                'id' => 'credit-card-' . $payment->id,
                'payment_id' => $payment->id,
                'type' => 'credit-card',
                'description' => $payment->creditCard?->name ?? 'Credit card payment',
                'amount' => (float) $payment->total_amount,
                'due_date' => $payment->due_date?->toDateString(),
                'status' => (string) $payment->status->value,
                'days_until_due' => $payment->due_date ? $today->diffInDays($payment->due_date, false) : null,
                'transaction_posted' => (bool) $payment->postingTransaction,
            ]);

        $subscriptions = Subscription::query()
            ->with(['account', 'creditCard', 'frequencyOption'])
            ->active()
            ->whereBetween('next_renewal_date', [$today, $until])
            ->where('user_id', $user->id)
            ->get()
            ->map(fn (Subscription $subscription) => [
                'id' => 'subscription-' . $subscription->id,
                'payment_id' => $subscription->id,
                'type' => 'subscription',
                'description' => $subscription->name,
                'amount' => $this->subscriptionService->getBillingAmount($subscription),
                'due_date' => $subscription->next_renewal_date?->toDateString(),
                'status' => (string) $subscription->status->value,
                'days_until_due' => $subscription->next_renewal_date ? $today->diffInDays($subscription->next_renewal_date, false) : null,
                'transaction_posted' => $subscription->next_renewal_date
                    ? $this->subscriptionService->hasPostingForRenewal($subscription, $subscription->next_renewal_date)
                    : false,
                'auto_create_transaction' => (bool) $subscription->auto_create_transaction,
                'payment_source_type' => $subscription->payment_source_type,
                'posting_target' => $subscription->payment_source_type === 'credit-card'
                    ? 'credit-card-expense'
                    : 'transaction',
                'frequency_label' => $subscription->frequency_label,
            ]);

        return collect($loanPayments)
            ->merge($creditCardPayments)
            ->merge($subscriptions)
            ->sortBy('due_date')
            ->values()
            ->all();
    }
}
