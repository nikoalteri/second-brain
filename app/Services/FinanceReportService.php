<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinanceReportService
{
    public function loadYears(?int $userId = null): array
    {
        $q = DB::table('transactions')
            ->selectRaw('YEAR(date) as year')
            ->whereNull('deleted_at');
        if ($userId) {
            $q->where('user_id', $userId);
        }
        return $q->groupBy('year')->orderByDesc('year')->pluck('year')->toArray();
    }

    public function baseTransactionsQuery(int $year, array $types = [], ?string $note = null, ?int $userId = null): Builder
    {
        $q = DB::table('transactions')
            ->where('transactions.is_transfer', false)
            ->whereNull('transactions.deleted_at')
            ->whereYear('transactions.date', $year);
        if ($userId) {
            $q->where('transactions.user_id', $userId);
        }
        if (!empty($types)) {
            $q->whereIn('transactions.transaction_type_id', $types);
        }
        if ($note !== null && $note !== '') {
            $q->where('transactions.notes', $note);
        }
        return $q;
    }

    public function getTypeOptions(): array
    {
        return TransactionType::orderBy('name')->pluck('name', 'id')->toArray();
    }

    public function getTable(int $year, ?int $userId = null): array
    {
        $rows = DB::table('transactions')
            ->leftJoin('transaction_types as tt', 'transactions.transaction_type_id', '=', 'tt.id')
            ->selectRaw('
                MONTH(transactions.date) as month,
                SUM(CASE WHEN tt.name IN ("Earnings", "Cashback") THEN transactions.amount ELSE 0 END) as earnings,
                SUM(CASE WHEN tt.name = "Expenses" THEN ABS(transactions.amount) ELSE 0 END) as expenses,
                SUM(CASE WHEN tt.name IN ("Earnings", "Cashback", "Expenses") THEN transactions.amount ELSE 0 END) as net
            ')
            ->where('transactions.is_transfer', false)
            ->whereNull('transactions.deleted_at')
            ->whereYear('transactions.date', $year);
        if ($userId) {
            $rows->where('transactions.user_id', $userId);
        }
        $rows = $rows->groupByRaw('MONTH(transactions.date)')
            ->orderByRaw('MONTH(transactions.date)')
            ->get()
            ->keyBy('month')
            ->toArray();

        $result = [];
        $totals = ['earnings' => 0, 'expenses' => 0, 'net' => 0];

        for ($m = 1; $m <= 12; $m++) {
            $row = $rows[$m] ?? (object)['earnings' => 0, 'expenses' => 0, 'net' => 0];
            $result[] = (object)[
                'month_name' => Carbon::create($year, $m)->translatedFormat('F'),
                'earnings'   => $row->earnings,
                'expenses'   => $row->expenses,
                'net'        => $row->net,
            ];
            $totals['earnings'] += $row->earnings;
            $totals['expenses'] += $row->expenses;
            $totals['net']      += $row->net;
        }

        $result[] = (object)[
            'month_name' => 'TOTALE',
            'earnings'   => $totals['earnings'],
            'expenses'   => $totals['expenses'],
            'net'        => $totals['net'],
        ];

        return $result;
    }

    // Altri metodi: getPivotData, getPieData, getDetailTransactions, getNoteOptions...
}
