<?php

namespace App\Services;

use App\Models\CategoryBudget;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class BudgetService
{
    public function __construct(
        private readonly BudgetAlertService $budgetAlertService,
    ) {
    }

    public function getMonthlyOverview(int $userId, int $year, int $month): array
    {
        $periodStart = $this->periodStart($year, $month);
        $categories = TransactionCategory::query()
            ->withoutUserScope()
            ->with('parent')
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->whereDoesntHave('children')
            ->orderBy('name')
            ->get();

        $spendByCategory = Transaction::query()
            ->withoutUserScope()
            ->selectRaw('transaction_category_id, COALESCE(SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END), 0) as spent_amount')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->where('is_transfer', false)
            ->whereNotNull('transaction_category_id')
            ->where('competence_month', $periodStart->format('Y-m'))
            ->groupBy('transaction_category_id')
            ->pluck('spent_amount', 'transaction_category_id');

        $budgetsByCategory = CategoryBudget::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->whereDate('period_start', $periodStart->toDateString())
            ->get()
            ->keyBy('transaction_category_id');

        return [
            'selected_year' => $year,
            'selected_month' => $month,
            'period_start' => $periodStart->toDateString(),
            'categories' => $categories
                ->map(fn (TransactionCategory $category) => $this->buildCategoryOverview(
                    $category,
                    $budgetsByCategory->get($category->id),
                    $spendByCategory
                ))
                ->values()
                ->all(),
        ];
    }

    public function findUserCategory(int $userId, int $categoryId): ?TransactionCategory
    {
        return TransactionCategory::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->find($categoryId);
    }

    public function isLeafCategory(TransactionCategory $category): bool
    {
        return ! $category->children()->exists();
    }

    public function upsertMonthlyBudget(int $userId, int $categoryId, int $year, int $month, float $amount): CategoryBudget
    {
        return CategoryBudget::query()
            ->withoutUserScope()
            ->updateOrCreate(
                [
                    'user_id' => $userId,
                    'transaction_category_id' => $categoryId,
                    'period_start' => $this->periodStart($year, $month)->toDateString(),
                ],
                [
                    'amount' => round($amount, 2),
                ],
            );
    }

    public function clearMonthlyBudget(int $userId, int $categoryId, int $year, int $month): void
    {
        CategoryBudget::query()
            ->withoutUserScope()
            ->where('user_id', $userId)
            ->where('transaction_category_id', $categoryId)
            ->whereDate('period_start', $this->periodStart($year, $month)->toDateString())
            ->delete();
    }

    private function buildCategoryOverview(
        TransactionCategory $category,
        ?CategoryBudget $budget,
        Collection $spendByCategory,
    ): array {
        $budgetAmount = $budget ? round((float) $budget->amount, 2) : null;
        $spentAmount = round((float) ($spendByCategory->get($category->id) ?? 0), 2);
        $usageRatio = $this->budgetAlertService->calculateUsageRatio($budgetAmount, $spentAmount);

        return [
            'transaction_category_id' => $category->id,
            'parent_name' => $category->parent?->name,
            'name' => $category->name,
            'is_leaf' => true,
            'budget_amount' => $budgetAmount,
            'spent_amount' => $spentAmount,
            'usage_ratio' => $usageRatio,
            'alert_status' => $this->budgetAlertService->resolveStatus($budgetAmount, $spentAmount),
        ];
    }

    private function periodStart(int $year, int $month): CarbonImmutable
    {
        return CarbonImmutable::create($year, $month, 1)->startOfMonth();
    }
}
