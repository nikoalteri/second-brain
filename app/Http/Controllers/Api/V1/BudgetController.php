<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BudgetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BudgetController extends Controller
{
    public function __construct(
        private readonly BudgetService $budgetService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'month' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);

        $year = (int) ($validated['year'] ?? now()->year);
        $month = (int) ($validated['month'] ?? now()->month);

        return response()->json(
            $this->budgetService->getMonthlyOverview($request->user()->id, $year, $month),
            200,
            [],
            JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    public function upsert(Request $request, int $transactionCategory): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        $this->assertLeafCategory($request->user()->id, $transactionCategory);

        $budget = $this->budgetService->upsertMonthlyBudget(
            $request->user()->id,
            $transactionCategory,
            $validated['year'],
            $validated['month'],
            (float) $validated['amount'],
        );

        return response()->json(
            [
                'transaction_category_id' => $budget->transaction_category_id,
                'period_start' => $budget->period_start->toDateString(),
                'amount' => (float) $budget->amount,
            ],
            200,
            [],
            JSON_PRESERVE_ZERO_FRACTION,
        );
    }

    public function destroy(Request $request, int $transactionCategory): JsonResponse
    {
        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);

        $this->assertLeafCategory($request->user()->id, $transactionCategory);

        $this->budgetService->clearMonthlyBudget(
            $request->user()->id,
            $transactionCategory,
            $validated['year'],
            $validated['month'],
        );

        return response()->json(status: 204);
    }

    private function assertLeafCategory(int $userId, int $transactionCategoryId): void
    {
        $category = $this->budgetService->findUserCategory($userId, $transactionCategoryId);

        if ($category === null || ! $this->budgetService->isLeafCategory($category)) {
            throw ValidationException::withMessages([
                'transaction_category_id' => 'Budgets can only be set for leaf categories owned by the authenticated user.',
            ]);
        }
    }
}
