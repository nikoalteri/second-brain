<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\CreditCardPayment;
use App\Models\LoanPayment;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    public function upcomingPayments(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        $days = (int) ($validated['days'] ?? 3);
        $today = now()->startOfDay();
        $until = now()->addDays($days)->endOfDay();
        $user = $request->user();

        $loanPayments = LoanPayment::query()
            ->with(['loan', 'postingTransaction'])
            ->whereBetween('due_date', [$today, $until])
            ->where('status', '!=', 'paid')
            ->when(
                ! $user->hasRole('superadmin'),
                fn ($query) => $query->whereHas('loan', fn ($loanQuery) => $loanQuery->where('user_id', $user->id))
            )
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
            ->when(
                ! $user->hasRole('superadmin'),
                fn ($query) => $query->whereHas('creditCard', fn ($cardQuery) => $cardQuery->where('user_id', $user->id))
            )
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
            ->when(
                ! $user->hasRole('superadmin'),
                fn ($query) => $query->where('user_id', $user->id)
            )
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

        return response()->json([
            'data' => collect($loanPayments)
                ->merge($creditCardPayments)
                ->merge($subscriptions)
                ->sortBy('due_date')
                ->values(),
        ]);
    }
}
