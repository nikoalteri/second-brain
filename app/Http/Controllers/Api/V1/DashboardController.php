<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\UpcomingPaymentsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly UpcomingPaymentsService $upcomingPaymentsService) {}

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

        return response()->json([
            'data' => $this->upcomingPaymentsService->forUser(
                $request->user(),
                (int) ($validated['days'] ?? 3),
            ),
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
            ->with([
                'category' => fn ($query) => $query->withoutUserScope(),
                'type',
            ])
            ->when(
                ! $request->user()->hasRole('superadmin'),
                fn ($query) => $query->where('transactions.user_id', $request->user()->id)
            )
            ->where('transactions.is_transfer', false)
            ->whereYear('transactions.date', $referenceDate->year)
            ->whereMonth('transactions.date', $referenceDate->month)
            ->whereNull('transactions.deleted_at')
            ->get()
            ->filter(fn (Transaction $transaction) => ! (bool) $transaction->type?->is_income)
            ->filter(fn (Transaction $transaction) => $this->shouldAppearInExpenseHighlights($transaction))
            ->groupBy(function (Transaction $transaction) {
                if ($transaction->category?->name) {
                    return $transaction->category->name;
                }

                if ($this->isCreditCardPaymentHighlight($transaction)) {
                    return 'Credit card payments';
                }

                return 'Uncategorised';
            })
            ->map(fn ($transactions, $category) => [
                'category' => $category,
                'total' => round((float) $transactions->sum(fn (Transaction $transaction) => abs((float) $transaction->amount)), 2),
                'count' => $transactions->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->toArray();
    }

    private function shouldAppearInExpenseHighlights(Transaction $transaction): bool
    {
        if ($this->isCreditCardPaymentHighlight($transaction)) {
            return true;
        }

        if ($transaction->loan_payment_id || $this->isPaymentLikeTransaction($transaction)) {
            return false;
        }

        return true;
    }

    private function isCreditCardPaymentHighlight(Transaction $transaction): bool
    {
        return (bool) $transaction->credit_card_payment_id;
    }

    private function isPaymentLikeTransaction(Transaction $transaction): bool
    {
        $typeName = strtolower(trim((string) ($transaction->type?->name ?? '')));
        $description = strtolower(trim((string) $transaction->description));

        if ($typeName !== '' && str_contains($typeName, 'payment')) {
            return true;
        }

        return $description !== '' && str_contains($description, 'payment');
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
