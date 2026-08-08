<?php

namespace App\Services;

use App\Enums\CreditCardPaymentStatus;
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
        $cycle->loadMissing(['creditCard', 'expenses', 'payments']);
        $card = $cycle->creditCard;

        if (!$card) {
            return [];
        }

        $startDate = $cycle->period_start_date;
        $endDate = $cycle->statement_date;
        $dailyBalances = [];

        // Group expenses by their posting date (posted_at) when available,
        // otherwise fall back to transaction date (spent_at).
        // Amex uses the contabilization/posting date for daily balance interest calculations.
        $expensesByDate = $cycle->expenses()
            ->orderBy('spent_at')
            ->get()
            ->groupBy(fn($e) => ($e->posted_at ?? $e->spent_at)->toDateString())
            ->map(fn($expenses) => $expenses->sum('amount'))
            ->toArray();

        // Only PAID payments actually reduce the outstanding principal — a PENDING payment has
        // not moved money yet. This matches syncCardBalance(), which sums principal_amount over
        // PAID payments only. Effective date mirrors CreditCardPaymentPostingService: actual_date
        // when present, else due_date. Only the PRINCIPAL portion reduces the capital; the
        // interest and stamp-duty portions are separate ledger lines and must not be subtracted.
        $paymentsByDate = $cycle->payments()
            ->where('status', CreditCardPaymentStatus::PAID)
            ->get()
            ->groupBy(fn ($p) => ($p->actual_date ?? $p->due_date)->toDateString())
            ->map(fn ($payments) => (float) $payments->sum('principal_amount'))
            ->toArray();

        // Ignore any payment whose effective date falls outside this cycle's window; it cannot
        // be replayed by the loop below and must not distort the opening balance either.
        $paymentsByDate = array_filter(
            $paymentsByDate,
            fn ($amount, $dateStr) => $dateStr >= $startDate->toDateString()
                && $dateStr <= $endDate->toDateString(),
            ARRAY_FILTER_USE_BOTH
        );

        // Starting balance = debt at start of cycle, before this cycle's expenses.
        // current_balance is the CURRENT (post-payment) figure. To reconstruct the balance at the
        // START of the cycle we undo this cycle's own mutations — subtract its expenses and add
        // back its repaid principal — then replay both, day by day, below. Without adding the
        // principal back the loop would subtract the same payment a second time and the walk
        // would end below current_balance.
        $cycleSpent = (float) ($cycle->total_spent ?? 0);
        $cyclePaidPrincipal = array_sum($paymentsByDate);
        $currentBalance = max(0.0, (float) $card->current_balance - $cycleSpent + $cyclePaidPrincipal);

        // Calculate balance for each day in the cycle
        $date = $startDate->copy();
        while ($date->lte($endDate)) {
            $dateStr = $date->toDateString();

            // Add expenses posted on this date
            if (isset($expensesByDate[$dateStr])) {
                $currentBalance += (float) $expensesByDate[$dateStr];
            }

            // Apply principal repaid on this date
            if (isset($paymentsByDate[$dateStr])) {
                $currentBalance -= (float) $paymentsByDate[$dateStr];
            }

            $currentBalance = max(0.0, $currentBalance);

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
     * Calculate total interest using the direct monthly method.
     *
     * Interest = current_balance × (annual_rate / 100 / 12)
     *
     * The stored rate is the ANNUAL nominal rate (TAN); this method charges a flat twelfth of
     * it per month. It is a deliberate simplification — use DAILY_BALANCE for day-precise
     * accrual. Before Phase 19 this applied the FULL annual rate every month (~12x too high).
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

        return round($currentBalance * ($annualRatePercent / 100 / 12), 2);
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
        $includesStampDuty = (bool) ($card->fixed_payment_includes_stamp_duty ?? false);
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
        if ($includesStampDuty) {
            // D-03 inclusive mode: the stamp duty is already inside the fixed payment, so it
            // consumes part of the installment. principal = installment - interest - stamp duty,
            // and total_due is the fixed payment itself (adding the duty on top would bill it twice).
            $effectiveInstallment = min($fixedPayment, $totalExposed + $interestAmount + $stampDuty);
            $principalAmount = round(max(0.0, $effectiveInstallment - $interestAmount - $stampDuty), 2);
            $totalDue = round($effectiveInstallment, 2);
        } else {
            // Exclusive mode (default): the stamp duty is charged on top of the fixed payment.
            $effectiveInstallment = min($fixedPayment, $totalExposed + $interestAmount);
            $principalAmount = round(max(0.0, $effectiveInstallment - $interestAmount), 2);
            $totalDue = round($effectiveInstallment + $stampDuty, 2);
        }

        $nextBalance = round(max(0.0, $totalExposed - $principalAmount), 2);

        return [
            'interest_amount' => $interestAmount,
            'principal_amount' => $principalAmount,
            'stamp_duty_amount' => round($stampDuty, 2),
            'installment_amount' => round($effectiveInstallment, 2),
            'total_due' => $totalDue,
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
