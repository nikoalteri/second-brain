<?php

namespace App\Services;

use Carbon\Carbon;

class FinanceReportSnapshotService
{
    public function __construct(
        private readonly FinanceReportService $financeReportService,
    ) {
    }

    public function build(int $year, array $types = [], ?string $note = null, ?int $userId = null): array
    {
        $table = $this->buildFilteredCashflowTable($year, $types, $note, $userId);
        $pivot = $this->financeReportService->getPivotData($year, $types, $note, $userId);
        $pie = $this->financeReportService->getPieData($year, $types, $note, $userId);

        return [
            'selected_year' => $year,
            'filters' => [
                'types' => $types,
                'note' => $note,
            ],
            'table' => $table,
            'pivot' => $pivot,
            'pie' => $pie,
            'export_sections' => [
                'cashflow' => [
                    'heading' => 'Cashflow Summary',
                    'rows' => $this->cashflowRows($table),
                ],
                'pivot' => [
                    'heading' => 'Category Pivot',
                    'rows' => $this->pivotRows($pivot),
                ],
                'distribution' => [
                    'heading' => 'Distribution',
                    'rows' => $this->distributionRows($pie),
                ],
            ],
            'xlsx_sections' => [
                'cashflow' => $this->cashflowRows($table),
                'pivot' => $this->pivotRows($pivot),
                'distribution' => $this->distributionRows($pie),
            ],
        ];
    }

    private function buildFilteredCashflowTable(int $year, array $types = [], ?string $note = null, ?int $userId = null): array
    {
        $rows = $this->financeReportService
            ->baseTransactionsQuery($year, $types, $note, $userId)
            ->leftJoin('transaction_types as tt', 'transactions.transaction_type_id', '=', 'tt.id')
            ->select(['transactions.date', 'transactions.amount', 'tt.is_income'])
            ->get();

        $groupedRows = [];

        foreach ($rows as $row) {
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
        $totals = ['earnings' => 0.0, 'expenses' => 0.0, 'net' => 0.0];

        for ($month = 1; $month <= 12; $month++) {
            $row = $groupedRows[$month] ?? (object) ['earnings' => 0.0, 'expenses' => 0.0, 'net' => 0.0];
            $result[] = (object) [
                'month_name' => Carbon::create($year, $month)->translatedFormat('F'),
                'earnings' => round($row->earnings, 2),
                'expenses' => round($row->expenses, 2),
                'net' => round($row->net, 2),
            ];
            $totals['earnings'] += $row->earnings;
            $totals['expenses'] += $row->expenses;
            $totals['net'] += $row->net;
        }

        $result[] = (object) [
            'month_name' => 'TOTALE',
            'earnings' => round($totals['earnings'], 2),
            'expenses' => round($totals['expenses'], 2),
            'net' => round($totals['net'], 2),
        ];

        return $result;
    }

    private function cashflowRows(array $table): array
    {
        $rows = [['Month', 'Earnings', 'Expenses', 'Net']];

        foreach ($table as $row) {
            $rows[] = [
                $row->month_name,
                $this->formatAmount($row->earnings),
                $this->formatAmount($row->expenses),
                $this->formatAmount($row->net),
            ];
        }

        return $rows;
    }

    private function pivotRows(array $pivot): array
    {
        $header = ['Category'];

        for ($month = 1; $month <= 12; $month++) {
            $header[] = Carbon::create(2026, $month, 1)->format('M');
        }

        $header[] = 'Total';

        $rows = [$header];

        foreach ($pivot['tree'] as $parent) {
            $parentData = $pivot['pivot'][$parent['key']] ?? [];
            $rows[] = $this->pivotRow($parent['label'], $parentData);

            if (! $parent['has_children']) {
                continue;
            }

            foreach ($parent['children'] as $child) {
                $childData = $pivot['pivot'][$child['key']] ?? [];
                $rows[] = $this->pivotRow('  ' . $child['label'], $childData);
            }
        }

        $grandTotalRow = ['GRAND TOTAL'];

        for ($month = 1; $month <= 12; $month++) {
            $grandTotalRow[] = $this->formatAmount($pivot['monthTotals'][$month] ?? 0);
        }

        $grandTotalRow[] = $this->formatAmount($pivot['grandTotal'] ?? 0);
        $rows[] = $grandTotalRow;

        return $rows;
    }

    private function distributionRows(array $pie): array
    {
        $rows = [['Category', 'Amount']];

        foreach ($pie as $item) {
            $rows[] = [
                $item['label'],
                $this->formatAmount($item['amount']),
            ];
        }

        return $rows;
    }

    private function pivotRow(string $label, array $data): array
    {
        $row = [$label];

        for ($month = 1; $month <= 12; $month++) {
            $row[] = $this->formatAmount($data[$month] ?? 0);
        }

        $row[] = $this->formatAmount($data['total'] ?? 0);

        return $row;
    }

    private function formatAmount(float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }
}
