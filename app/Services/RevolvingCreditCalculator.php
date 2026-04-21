<?php

namespace App\Services;

use App\Enums\InterestCalculationMethod;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use Carbon\Carbon;

class RevolvingCreditCalculator
{
    /**
     * Check if this is the first billing cycle (earliest issued/paid cycle)
     */
    public function isFirstCycle(CreditCard $card, CreditCardCycle $cycle): bool
    {
        $firstIssuedCycle = $card->cycles()
            ->whereIn('status', ['issued', 'paid', 'overdue'])
            ->orderBy('statement_date')
            ->first();

        return $firstIssuedCycle && $firstIssuedCycle->id === $cycle->id;
    }

    /**
     * Calculate daily balances for a cycle
     * 
     * @param CreditCardCycle $cycle
     * @return array Key: date string (Y-m-d), Value: balance at end of day
     */
    public function calculateDailyBalances(CreditCardCycle $cycle): array
    {
        $cycle->loadMissing(['creditCard', 'expenses']);
        $card = $cycle->creditCard;
        
        if (!$card) {
            return [];
        }

        $startDate = $cycle->period_start_date;
        $endDate = $cycle->statement_date;
        $dailyBalances = [];
        
        // Starting balance = debt at start of cycle, before this cycle's expenses
        $cycleSpent = (float) ($cycle->total_spent ?? 0);
        $currentBalance = max(0.0, (float) $card->current_balance - $cycleSpent);
        
        // Group expenses by date
        $expensesByDate = $cycle->expenses()
            ->orderBy('spent_at')
            ->get()
            ->groupBy(fn($e) => $e->spent_at->toDateString())
            ->map(fn($expenses) => $expenses->sum('amount'))
            ->toArray();
        
        // Calculate balance for each day in the cycle
        $date = $startDate->copy();
        while ($date->lte($endDate)) {
            $dateStr = $date->toDateString();
            
            // Add expenses posted on this date
            if (isset($expensesByDate[$dateStr])) {
                $currentBalance += (float) $expensesByDate[$dateStr];
            }
            
            // Store daily balance (end of day)
            $dailyBalances[$dateStr] = round($currentBalance, 2);
            $date->addDay();
        }
        
        return $dailyBalances;
    }

    /**
     * Calculate total interest from daily balances using daily balance method
     * 
     * Interest = sum of (daily_balance * (annual_rate / 365)) for each day
     * 
     * @param array $dailyBalances Keyed by date string
     * @param float $annualRatePercent Annual interest rate as percentage (e.g., 14 for 14%)
     * @return float Total interest amount
     */
    public function calculateInterestFromDailyBalances(
        array $dailyBalances,
        float $annualRatePercent
    ): float {
        if (empty($dailyBalances)) {
            return 0.0;
        }

        if ($annualRatePercent <= 0) {
            return 0.0;
        }

        // Convert annual percentage to daily rate
        // 14% annual = 0.14 / 365 daily
        $dailyRate = $annualRatePercent / 100 / 365;
        $totalInterest = 0.0;

        foreach ($dailyBalances as $balance) {
            $totalInterest += (float) $balance * $dailyRate;
        }

        return round($totalInterest, 2);
    }

    /**
     * Calculate total interest using direct monthly method
     * 
     * Interest = current_balance × (annual_rate / 100)
     * This applies the annual rate directly as a monthly charge
     * 
     * @param float $currentBalance
     * @param float $annualRatePercent
     * @return float Total interest amount
     */
    public function calculateInterestDirectMonthly(
        float $currentBalance,
        float $annualRatePercent
    ): float {
        if ($currentBalance <= 0 || $annualRatePercent <= 0) {
            return 0.0;
        }

        return round($currentBalance * ($annualRatePercent / 100), 2);
    }

    /**
     * Calculate payment breakdown for a revolving card cycle
     * 
     * This uses either daily balance or direct monthly method based on card configuration:
     * - First cycle always has 0 interest
     * - Subsequent cycles calculate interest using configured method
     * - Fixed payment is split: Interest + Principal
     * - Bollo (stamp duty) is separate, not deducted from payment
     * 
     * @param CreditCardCycle $cycle
     * @return array Breakdown with keys: interest_amount, principal_amount, stamp_duty_amount, etc.
     */
    public function calculatePaymentBreakdown(CreditCardCycle $cycle): array
    {
        $cycle->loadMissing('creditCard');
        $card = $cycle->creditCard;

        if (!$card) {
            return [];
        }

        $fixedPayment = (float) ($card->fixed_payment ?? 0);
        $annualRate = (float) ($card->interest_rate ?? 0);
        $stampDuty = (float) ($card->stamp_duty_amount ?? 0);
        $currentDebt = max(0.0, (float) $card->current_balance);
        $cycleSpent = (float) ($cycle->total_spent ?? 0);

        // Total exposure = current debt (already includes cycle expenses via observer)
        $totalExposed = $currentDebt;

        if ($totalExposed <= 0 || $fixedPayment <= 0) {
            return [
                'interest_amount' => 0.0,
                'principal_amount' => 0.0,
                'stamp_duty_amount' => round($stampDuty, 2),
                'installment_amount' => 0.0,
                'total_due' => round($stampDuty, 2),
                'next_balance' => 0.0,
            ];
        }

        // Calculate interest based on method
        $interestAmount = 0.0;
        if (!$this->isFirstCycle($card, $cycle)) {
            // Not first cycle: calculate interest based on configured method
            $method = $card->interest_calculation_method ?? InterestCalculationMethod::DAILY_BALANCE;
            
            if ($method === InterestCalculationMethod::DIRECT_MONTHLY) {
                // Direct monthly: apply annual rate directly
                $interestAmount = $this->calculateInterestDirectMonthly($currentDebt, $annualRate);
            } else {
                // Daily balance: sum daily calculations
                $dailyBalances = $this->calculateDailyBalances($cycle);
                $interestAmount = $this->calculateInterestFromDailyBalances($dailyBalances, $annualRate);
            }
        }
        // else: First cycle has 0 interest by definition

        // Split payment: Interest + Principal
        // Payment cannot exceed total exposure
        $effectiveInstallment = min($fixedPayment, $totalExposed + $interestAmount);
        $principalAmount = round(max(0.0, $effectiveInstallment - $interestAmount), 2);
        $nextBalance = round(max(0.0, $totalExposed - $principalAmount), 2);

        return [
            'interest_amount' => $interestAmount,
            'principal_amount' => $principalAmount,
            'stamp_duty_amount' => round($stampDuty, 2),
            'installment_amount' => round($effectiveInstallment, 2),
            'total_due' => round($effectiveInstallment + $stampDuty, 2),
            'next_balance' => $nextBalance,
        ];
    }

    /**
     * Calculate charge card cycle (pay full amount, no interest)
     */
    public function calculateChargePaymentBreakdown(CreditCardCycle $cycle): array
    {
        $cycle->loadMissing('creditCard');
        $card = $cycle->creditCard;

        $stampDuty = (float) ($card->stamp_duty_amount ?? 0);
        $cycleSpent = (float) ($cycle->total_spent ?? 0);

        return [
            'interest_amount' => 0.0,
            'principal_amount' => round(max(0.0, $cycleSpent), 2),
            'stamp_duty_amount' => round($stampDuty, 2),
            'installment_amount' => round(max(0.0, $cycleSpent), 2),
            'total_due' => round($cycleSpent + $stampDuty, 2),
            'next_balance' => 0.0,
        ];
    }
}
