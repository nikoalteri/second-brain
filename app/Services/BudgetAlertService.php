<?php

namespace App\Services;

class BudgetAlertService
{
    public function calculateUsageRatio(?float $budgetAmount, float $spentAmount): ?float
    {
        if ($budgetAmount === null || $budgetAmount <= 0) {
            return null;
        }

        return round($spentAmount / $budgetAmount, 4);
    }

    public function resolveStatus(?float $budgetAmount, float $spentAmount): string
    {
        $ratio = $this->calculateUsageRatio($budgetAmount, $spentAmount);

        return match (true) {
            $ratio === null => 'none',
            $ratio >= 1.2 => 'critical',
            $ratio >= 1.0 => 'exceeded',
            $ratio >= 0.8 => 'warning',
            default => 'ok',
        };
    }
}
