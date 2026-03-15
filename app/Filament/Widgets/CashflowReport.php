<?php

namespace App\Filament\Widgets;

use Illuminate\Support\Facades\DB;
use Filament\Widgets\Widget;

class CashflowReport extends Widget
{
    protected string $view = 'filament.widgets.cashflow-report';

    protected int | string | array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $rows = DB::table('transactions')
            ->leftJoin('transaction_types as tt', 'transactions.transaction_type_id', '=', 'tt.id')
            ->selectRaw('
                YEAR(transactions.date) as year,
                MONTH(transactions.date) as month,
                SUM(CASE WHEN tt.name IN ("Earnings", "Cashback") THEN transactions.amount ELSE 0 END) as earnings,
                SUM(CASE WHEN tt.name = "Expenses" THEN ABS(transactions.amount) ELSE 0 END) as expenses,
                SUM(CASE WHEN tt.name IN ("Earnings", "Cashback", "Expenses") THEN transactions.amount ELSE 0 END) as net
            ')
            ->where('transactions.is_transfer', false)
            ->whereNull('transactions.deleted_at')
            ->groupByRaw('YEAR(transactions.date), MONTH(transactions.date)')
            ->orderByRaw('YEAR(transactions.date) DESC, MONTH(transactions.date) DESC')
            ->limit(12)
            ->get();

        $totalEarnings = $rows->sum('earnings');
        $totalExpenses = $rows->sum('expenses');
        $totalNet      = $rows->sum('net');

        return [
            'rows'          => $rows,
            'totalEarnings' => $totalEarnings,
            'totalExpenses' => $totalExpenses,
            'totalNet'      => $totalNet,
        ];
    }
}
