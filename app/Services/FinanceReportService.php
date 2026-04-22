<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class FinanceReportService
{
    public function loadYears(?int $userId = null): array
    {
        $q = DB::table('transactions')
            ->select('date')
            ->whereNull('deleted_at');
        if ($userId) {
            $q->where('user_id', $userId);
        }

        return $q->orderByDesc('date')
            ->pluck('date')
            ->filter()
            ->map(fn ($date) => Carbon::parse($date)->year)
            ->unique()
            ->values()
            ->all();
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
            ->select(['transactions.date', 'transactions.amount', 'tt.is_income'])
            ->where('transactions.is_transfer', false)
            ->whereNull('transactions.deleted_at')
            ->whereYear('transactions.date', $year);
        if ($userId) {
            $rows->where('transactions.user_id', $userId);
        }

        $groupedRows = [];

        foreach ($rows->get() as $row) {
            $month = Carbon::parse($row->date)->month;

            if (! isset($groupedRows[$month])) {
                $groupedRows[$month] = (object) [
                    'earnings' => 0.0,
                    'expenses' => 0.0,
                    'net' => 0.0,
                ];
            }

            if ($row->is_income) {
                $groupedRows[$month]->earnings += (float) $row->amount;
            } else {
                $groupedRows[$month]->expenses += abs((float) $row->amount);
            }

            $groupedRows[$month]->net += (float) $row->amount;
        }

        $result = [];
        $totals = ['earnings' => 0, 'expenses' => 0, 'net' => 0];

        for ($m = 1; $m <= 12; $m++) {
            $row = $groupedRows[$m] ?? (object)['earnings' => 0, 'expenses' => 0, 'net' => 0];
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

    public function getNoteOptions(int $year, ?int $userId = null): array
    {
        $query = DB::table('transactions')
            ->select('notes')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->whereYear('date', $year);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->distinct()->orderBy('notes')->limit(100)->pluck('notes', 'notes')->toArray();
    }

    public function getPivotData(int $year, array $types = [], ?string $note = null, ?int $userId = null): array
    {
        $rows = $this->baseTransactionsQuery($year, $types, $note, $userId)
            ->leftJoin('transaction_categories as tc', 'transactions.transaction_category_id', '=', 'tc.id')
            ->leftJoin('transaction_categories as tcp', 'tc.parent_id', '=', 'tcp.id')
            ->select([
                'transactions.date',
                'transactions.amount',
                'tc.name as category_name',
                'tc.parent_id as category_parent_id',
                'tcp.name as parent_name',
            ])
            ->get();

        $pivot = [];
        $childrenByParent = [];
        $monthTotals = array_fill(1, 12, 0);

        foreach ($rows as $row) {
            $month = Carbon::parse($row->date)->month;
            $parentName = $row->parent_name ?: ($row->category_name ?: 'Altro');
            $childName = $row->category_parent_id ? $row->category_name : null;
            $key = $childName ? "{$parentName}|{$childName}" : $parentName;
            $amount = (float) $row->amount;

            $pivot[$key][$month] = ($pivot[$key][$month] ?? 0) + $amount;
            $pivot[$key]['total'] = ($pivot[$key]['total'] ?? 0) + $amount;
            $monthTotals[$month] += $amount;

            if ($childName) {
                $childrenByParent[$parentName] ??= [];

                if (! in_array($childName, $childrenByParent[$parentName], true)) {
                    $childrenByParent[$parentName][] = $childName;
                }
            }
        }

        $parentTotals = [];

        foreach ($pivot as $key => $data) {
            $parentName = str_contains($key, '|') ? explode('|', $key)[0] : $key;
            $parentTotals[$parentName] = ($parentTotals[$parentName] ?? 0) + ($data['total'] ?? 0);
        }

        uasort($parentTotals, fn ($a, $b) => abs($b) <=> abs($a));

        $tree = [];

        foreach (array_keys($parentTotals) as $parentName) {
            $children = [];

            foreach ($childrenByParent[$parentName] ?? [] as $childName) {
                $childKey = "{$parentName}|{$childName}";

                if (isset($pivot[$childKey])) {
                    $children[] = ['key' => $childKey, 'label' => $childName];
                }
            }

            usort($children, fn ($a, $b) => abs($pivot[$b['key']]['total'] ?? 0) <=> abs($pivot[$a['key']]['total'] ?? 0));

            $tree[] = [
                'key' => $parentName,
                'label' => $parentName,
                'has_children' => ! empty($children),
                'children' => $children,
            ];
        }

        return [
            'tree' => $tree,
            'pivot' => $pivot,
            'monthTotals' => $monthTotals,
            'grandTotal' => array_sum($monthTotals),
        ];
    }

    public function getPieData(int $year, array $types = [], ?string $note = null, ?int $userId = null): array
    {
        $pivot = $this->getPivotData($year, $types, $note, $userId);
        $data = [];

        foreach ($pivot['tree'] as $node) {
            $total = $pivot['pivot'][$node['key']]['total'] ?? 0;

            if ($node['has_children']) {
                foreach ($node['children'] as $child) {
                    $total += $pivot['pivot'][$child['key']]['total'] ?? 0;
                }
            }

            if ($total != 0) {
                $data[] = [
                    'label' => $node['label'],
                    'amount' => round(abs($total), 2),
                ];
            }
        }

        return $data;
    }

    public function getDetailTransactions(int $year, int $month, string $categoryKey, array $types = [], ?string $note = null, ?int $userId = null): Collection
    {
        $query = Transaction::withoutGlobalScopes()
            ->with(['account', 'type'])
            ->leftJoin('transaction_categories as tc', 'transactions.transaction_category_id', '=', 'tc.id')
            ->leftJoin('transaction_categories as tcp', 'tc.parent_id', '=', 'tcp.id')
            ->select('transactions.*')
            ->where('transactions.is_transfer', false)
            ->whereNull('transactions.deleted_at')
            ->whereYear('transactions.date', $year)
            ->whereMonth('transactions.date', $month);

        if ($userId) {
            $query->where('transactions.user_id', $userId);
        }

        if (! empty($types)) {
            $query->whereIn('transactions.transaction_type_id', $types);
        }

        if ($note !== null && $note !== '') {
            $query->where('transactions.notes', $note);
        }

        if (str_contains($categoryKey, '|')) {
            [$parentName, $childName] = explode('|', $categoryKey, 2);

            $query
                ->where('tc.name', $childName)
                ->where('tcp.name', $parentName);
        } elseif ($categoryKey === 'Altro') {
            $query->whereNull('transactions.transaction_category_id');
        } else {
            $query->where(function (EloquentBuilder $categoryQuery) use ($categoryKey) {
                $categoryQuery
                    ->where(function (EloquentBuilder $directCategoryQuery) use ($categoryKey) {
                        $directCategoryQuery
                            ->where('tc.name', $categoryKey)
                            ->whereNull('tc.parent_id');
                    })
                    ->orWhere('tcp.name', $categoryKey);
            });
        }

        return $query->orderBy('date')->orderBy('id')->get();
    }
}
