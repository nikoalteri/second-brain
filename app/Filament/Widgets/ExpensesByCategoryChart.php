<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use App\Enums\TransactionType;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class ExpensesByCategoryChart extends ChartWidget
{
    public function getHeading(): ?string
    {
        return 'Expenses by Category (This Month)';
    }

    protected int|string|array $columnSpan = 'half';

    protected function getData(): array
    {
        $user = Auth::user();
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $expenses = Transaction::where('user_id', $user->id)
            ->whereHas('transactionType', fn($q) => $q->where('name', 'Expense'))
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->with('category')
            ->get()
            ->groupBy('category.name')
            ->map(fn($items) => $items->sum('amount'));

        return [
            'datasets' => [
                [
                    'label' => 'Amount',
                    'data' => $expenses->values()->toArray(),
                    'backgroundColor' => [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF',
                        '#FF9F40',
                    ],
                    'borderColor' => '#fff',
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $expenses->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
