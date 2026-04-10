<?php

namespace App\Services;

use App\Models\TripBudget;
use App\Models\TripExpense;
use App\Models\TripParticipant;

class TravelBudgetCalculator
{
    /**
     * Calculate total expenses for a budget.
     *
     * @param TripBudget $budget
     * @return float
     */
    public function totalExpenses(TripBudget $budget): float
    {
        return (float) $budget->expenses()->sum('amount');
    }

    /**
     * Calculate remaining budget balance.
     *
     * @param TripBudget $budget
     * @return float (can be negative if over budget)
     */
    public function remainingBudget(TripBudget $budget): float
    {
        return (float) $budget->initial_amount - $this->totalExpenses($budget);
    }

    /**
     * Calculate percentage of budget used (0-100).
     *
     * @param TripBudget $budget
     * @return float
     */
    public function budgetPercentageUsed(TripBudget $budget): float
    {
        if ((float) $budget->initial_amount == 0) {
            return 0.0;
        }

        $percentage = ($this->totalExpenses($budget) / (float) $budget->initial_amount) * 100;
        return round($percentage, 2);
    }

    /**
     * Add an expense to the budget.
     *
     * @param TripBudget $budget
     * @param TripParticipant $participant
     * @param float $amount
     * @param string $currency
     * @param string|null $category
     * @return TripExpense
     */
    public function addExpense(
        TripBudget $budget,
        TripParticipant $participant,
        float $amount,
        string $currency = 'USD',
        ?string $category = null
    ): TripExpense {
        return TripExpense::create([
            'user_id' => $budget->user_id,
            'trip_participant_id' => $participant->id,
            'trip_budget_id' => $budget->id,
            'amount' => $amount,
            'currency' => $currency,
            'category' => $category,
            'date' => now()->toDateString(),
        ]);
    }
}
