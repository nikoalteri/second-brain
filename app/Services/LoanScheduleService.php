<?php

namespace App\Services;

use App\Models\Loan;
use App\Models\LoanPayment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\Concerns\HasWorkdayCalculation;

class LoanScheduleService
{
    use HasWorkdayCalculation;

    public function generate(Loan $loan, bool $onlyMissing = true): void
    {
        DB::transaction(function () use ($loan, $onlyMissing) {
            if (! $onlyMissing) {
                $loan->payments()
                    ->where('status', 'pending')
                    ->delete();
            }

            $existingDates = $loan->payments()
                ->pluck('due_date')
                ->map(fn($date) => Carbon::parse($date)->format('Y-m-d'))
                ->all();

            $baseDate = Carbon::parse($loan->start_date);

            LoanPayment::withoutEvents(function () use ($loan, $existingDates, $baseDate) {
                for ($i = 0; $i < $loan->total_installments; $i++) {
                    $monthDate = $baseDate->copy()->addMonthsNoOverflow($i);

                    $dueDate = $monthDate->copy()->day(
                        min($loan->withdrawal_day, $monthDate->daysInMonth)
                    );

                    $dueDate = $this->adjustToWorkday($dueDate, (bool) $loan->skip_weekends);

                    if (in_array($dueDate->format('Y-m-d'), $existingDates, true)) {
                        continue;
                    }

                    $loan->payments()->create([
                        'due_date' => $dueDate,
                        'amount' => $loan->monthly_payment,
                        'interest_rate' => $loan->is_variable_rate ? null : $loan->interest_rate,
                        'status' => 'pending',
                    ]);
                }
            });

            $freshLoan = $loan->fresh(['payments.loan']);

            $this->syncLoan($freshLoan);
            app(LoanPaymentPostingService::class)->syncDuePayments(loan: $freshLoan);
        });
    }

    public function syncLoan(Loan $loan): void
    {
        $paidInstallments = $loan->payments()->where('status', 'paid')->count();
        $remainingAmount  = $this->calcOutstandingPrincipal($loan, $paidInstallments);

        $status = $remainingAmount <= 0
            ? 'completed'
            : ($loan->status === 'defaulted' ? 'defaulted' : 'active');

        $loan->update([
            'paid_installments' => $paidInstallments,
            'remaining_amount'  => $remainingAmount,
            'status'            => $status,
        ]);
    }

    public function recalculateFuturePayments(Loan $loan, float $newRate): void
    {
        DB::transaction(function () use ($loan, $newRate) {
            $paidInstallments = $loan->payments()->where('status', 'paid')->count();
            $remainingCapital  = $this->calcOutstandingPrincipal($loan, $paidInstallments);

            $pendingCount = $loan->payments()->where('status', 'pending')->count();

            if ($pendingCount <= 0 || $remainingCapital <= 0) {
                return;
            }

            $newMonthly = $this->calcMonthlyPayment($remainingCapital, $newRate, $pendingCount);

            LoanPayment::withoutEvents(function () use ($loan, $newRate, $newMonthly) {
                $loan->payments()
                    ->where('status', 'pending')
                    ->update([
                        'amount' => $newMonthly,
                        'interest_rate' => $newRate,
                    ]);
            });

            $loan->update([
                'interest_rate' => $newRate,
                'monthly_payment' => $newMonthly,
            ]);

            $this->syncLoan($loan->fresh(['payments.loan']));
        });
    }

    private function calcMonthlyPayment(float $total, float $rate, int $n): float
    {
        if ($n <= 0 || $total <= 0) {
            return 0;
        }

        if ($rate > 0) {
            $r = ($rate / 100) / 12;
            return round(($total * $r * (1 + $r) ** $n) / ((1 + $r) ** $n - 1), 2);
        }

        return round($total / $n, 2);
    }

    /**
     * Calculate the outstanding principal balance after a given number of paid installments.
     * For fixed-rate loans uses the standard amortization formula.
     * For variable-rate loans reconstructs the balance step-by-step from each paid payment.
     */
    private function calcOutstandingPrincipal(Loan $loan, int $paidCount): float
    {
        $total = (float) $loan->total_amount;
        $mp    = (float) $loan->monthly_payment;

        if ($loan->is_variable_rate) {
            $paidPayments = $loan->payments()
                ->where('status', 'paid')
                ->orderBy('due_date')
                ->get();

            $balance = $total;
            foreach ($paidPayments as $payment) {
                $r         = ((float) ($payment->interest_rate ?? 0) / 100) / 12;
                $interest  = $r > 0 ? $balance * $r : 0.0;
                $principal = max(0.0, (float) $payment->amount - $interest);
                $balance   = max(0.0, $balance - $principal);
            }

            return round($balance, 2);
        }

        $rate = (float) ($loan->interest_rate ?? 0);

        if ($rate > 0 && $paidCount > 0) {
            $r = ($rate / 100) / 12;
            return max(0.0, round(
                $total * (1 + $r) ** $paidCount - $mp * ((1 + $r) ** $paidCount - 1) / $r,
                2
            ));
        }

        // k=0 → full principal; rate=0 → simple principal subtraction
        return max(0.0, round($total - $paidCount * $mp, 2));
    }
}
