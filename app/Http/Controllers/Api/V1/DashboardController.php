<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CreditCardPayment;
use App\Models\LoanPayment;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptionService) {}

    public function charts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $referenceDate = Carbon::create(
            (int) ($validated['year'] ?? now()->year),
            (int) ($validated['month'] ?? now()->month),
            1,
        )->startOfMonth();

        $cashflow = $this->getMonthlyCashflowChartData($request, $referenceDate);
        $expenseCategories = $this->getExpenseCategoriesChartData($request, $referenceDate);
        $netWorthTrend = $this->getNetWorthTrendChartData($request, $referenceDate);

        return response()->json([
            'data' => [
                'month_label' => $referenceDate->format('F'),
                'cashflow' => $cashflow,
                'expense_categories' => $expenseCategories,
                'net_worth_trend' => $netWorthTrend,
            ],
        ]);
    }

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

    private function getMonthlyCashflowChartData(Request $request, Carbon $referenceDate): array
    {
        $transactions = Transaction::query()
            ->with('type')
            ->when(
                ! $request->user()->hasRole('superadmin'),
                fn ($query) => $query->where('user_id', $request->user()->id)
            )
            ->where('is_transfer', false)
            ->whereYear('date', $referenceDate->year)
            ->whereMonth('date', $referenceDate->month)
            ->get();

        $income = (float) $transactions
            ->filter(fn (Transaction $transaction) => (bool) $transaction->type?->is_income)
            ->sum('amount');

        $expenses = (float) $transactions
            ->filter(fn (Transaction $transaction) => ! $this->isPaymentTransaction($transaction))
            ->filter(fn (Transaction $transaction) => ! (bool) $transaction->type?->is_income)
            ->sum(fn (Transaction $transaction) => abs((float) $transaction->amount));

        $payments = (float) $transactions
            ->filter(fn (Transaction $transaction) => $this->isPaymentTransaction($transaction))
            ->sum(fn (Transaction $transaction) => abs((float) $transaction->amount));

        return [
            'income' => round($income, 2),
            'expenses' => round($expenses, 2),
            'payments' => round($payments, 2),
            'net' => round($income - $expenses - $payments, 2),
        ];
    }

    private function getExpenseCategoriesChartData(Request $request, Carbon $referenceDate): array
    {
        return Transaction::withoutGlobalScopes()
            ->leftJoin('transaction_categories', 'transactions.transaction_category_id', '=', 'transaction_categories.id')
            ->leftJoin('transaction_types', 'transactions.transaction_type_id', '=', 'transaction_types.id')
            ->when(
                ! $request->user()->hasRole('superadmin'),
                fn ($query) => $query->where('transactions.user_id', $request->user()->id)
            )
            ->where('transactions.is_transfer', false)
            ->whereYear('transactions.date', $referenceDate->year)
            ->whereMonth('transactions.date', $referenceDate->month)
            ->where('transaction_types.is_income', false)
            ->whereNull('transactions.loan_payment_id')
            ->whereNull('transactions.credit_card_payment_id')
            ->whereRaw('LOWER(COALESCE(transaction_types.name, "")) NOT LIKE ?', ['%payment%'])
            ->whereNull('transactions.deleted_at')
            ->selectRaw('COALESCE(transaction_categories.name, ?) as category, SUM(ABS(transactions.amount)) as total, COUNT(*) as cnt', ['Uncategorised'])
            ->groupBy('transactions.transaction_category_id', 'transaction_categories.name')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'category' => $row->category,
                'total' => (float) $row->total,
                'count' => (int) $row->cnt,
            ])
            ->toArray();
    }

    private function getNetWorthTrendChartData(Request $request, Carbon $referenceDate): array
    {
        $referenceMonthEnd = $referenceDate->copy()->endOfMonth();

        return collect(range(11, 0))
            ->map(function (int $monthsAgo) use ($request, $referenceDate, $referenceMonthEnd) {
                $monthStart = $referenceDate->copy()->subMonths($monthsAgo)->startOfMonth();
                $monthEnd = $monthStart->copy()->endOfMonth();

                $accounts = Account::query()
                    ->when(
                        ! $request->user()->hasRole('superadmin'),
                        fn ($query) => $query->where('user_id', $request->user()->id)
                    )
                    ->whereNotIn('type', ['debt', 'credit_card'])
                    ->where('created_at', '<=', $monthEnd)
                    ->get(['id', 'balance']);

                $netWorth = (float) $accounts->sum('balance');
                $accountIds = $accounts->pluck('id');

                if ($accountIds->isNotEmpty()) {
                    $netWorth -= (float) Transaction::query()
                        ->when(
                            ! $request->user()->hasRole('superadmin'),
                            fn ($query) => $query->where('user_id', $request->user()->id)
                        )
                        ->whereIn('account_id', $accountIds)
                        ->whereDate('date', '>', $monthEnd->toDateString())
                        ->whereDate('date', '<=', $referenceMonthEnd->toDateString())
                        ->sum('amount');
                }

                return [
                    'label' => $monthStart->format('M Y'),
                    'value' => round($netWorth, 2),
                ];
            })
            ->values()
            ->all();
    }

    private function isPaymentTransaction(Transaction $transaction): bool
    {
        if ($transaction->loan_payment_id || $transaction->credit_card_payment_id) {
            return true;
        }

        return str_contains(strtolower((string) ($transaction->type?->name ?? '')), 'payment');
    }
}
