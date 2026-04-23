<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\FinanceReportExportService;
use App\Services\FinanceReportSnapshotService;
use App\Services\FinanceReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FinanceReportController extends Controller
{
    public function __construct(
        private readonly FinanceReportService $financeReportService,
        private readonly FinanceReportSnapshotService $financeReportSnapshotService,
        private readonly FinanceReportExportService $financeReportExportService,
    ) {
    }

    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'types' => ['nullable', 'array'],
            'types.*' => ['integer'],
            'note' => ['nullable', 'string'],
        ]);

        $userId = $request->user()->hasRole('superadmin')
            ? null
            : $request->user()->id;

        $years = $this->financeReportService->loadYears($userId);
        $year = (int) ($validated['year'] ?? ($years[0] ?? now()->year));
        $types = $validated['types'] ?? [];
        $note = $validated['note'] ?? null;

        return response()->json([
            'years' => $years,
            'selected_year' => $year,
            'type_options' => $this->financeReportService->getTypeOptions(),
            'note_options' => $this->financeReportService->getNoteOptions($year, $userId),
            'table' => $this->financeReportService->getTable($year, $userId),
            'pivot' => $this->financeReportService->getPivotData($year, $types, $note, $userId),
            'pie' => $this->financeReportService->getPieData($year, $types, $note, $userId),
        ]);
    }

    public function details(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'category_key' => ['required', 'string'],
            'types' => ['nullable', 'array'],
            'types.*' => ['integer'],
            'note' => ['nullable', 'string'],
        ]);

        $userId = $request->user()->hasRole('superadmin')
            ? null
            : $request->user()->id;

        $transactions = $this->financeReportService->getDetailTransactions(
            $validated['year'],
            $validated['month'],
            $validated['category_key'],
            $validated['types'] ?? [],
            $validated['note'] ?? null,
            $userId,
        );

        return response()->json([
            'transactions' => $transactions->map(fn ($transaction) => [
                'id' => $transaction->id,
                'date' => $transaction->date?->format('Y-m-d'),
                'description' => $transaction->description,
                'account_name' => $transaction->account?->name,
                'amount' => (float) $transaction->amount,
            ])->values(),
            'total' => (float) $transactions->sum('amount'),
        ]);
    }

    public function export(Request $request): Response
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'types' => ['nullable', 'array'],
            'types.*' => ['integer'],
            'note' => ['nullable', 'string'],
            'format' => ['required', 'in:csv,xlsx,pdf'],
        ]);

        $userId = $request->user()->hasRole('superadmin')
            ? null
            : $request->user()->id;

        $years = $this->financeReportService->loadYears($userId);
        $year = (int) ($validated['year'] ?? ($years[0] ?? now()->year));

        $snapshot = $this->financeReportSnapshotService->build(
            $year,
            $validated['types'] ?? [],
            $validated['note'] ?? null,
            $userId,
        );

        return $this->financeReportExportService->export(
            $snapshot,
            $validated['format'],
        );
    }
}
