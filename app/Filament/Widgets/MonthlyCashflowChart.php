<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MonthlyCashflowChart extends ChartWidget
{
    public function getHeading(): ?string
    {
        return 'Monthly Cashflow (This Month)';
    }

    protected int|string|array $columnSpan = 'half';

    protected function getData(): array
    {
        $user = Auth::user();
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Income
        $income = Transaction::where('user_id', $user->id)
            ->whereHas('transactionType', fn($q) => $q->where('name', 'Income'))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('is_transfer', false)
            ->sum('amount');

        // Expenses
        $expenses = Transaction::where('user_id', $user->id)
            ->whereHas('transactionType', fn($q) => $q->where('name', 'Expense'))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('is_transfer', false)
            ->sum('amount');

        // Payments (loan + cc)
        $payments = Transaction::where('user_id', $user->id)
            ->whereHas('transactionType', fn($q) => $q->where('name', 'Payment'))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->where('is_transfer', false)
            ->sum('amount');

        return [
            'datasets' => [
                [
                    'label' => 'Income',
                    'data' => [$income],
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => 'Expenses',
                    'data' => [$expenses],
                    'backgroundColor' => '#ef4444',
                ],
                [
                    'label' => 'Payments',
                    'data' => [$payments],
                    'backgroundColor' => '#f59e0b',
                ],
            ],
            'labels' => ['This Month'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
