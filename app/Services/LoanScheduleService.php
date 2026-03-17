<?php

namespace App\Services;

use App\Models\Loan;
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
                    'status' => 'pending',
                ]);
            }

            $this->syncLoan($loan->fresh(['payments.loan']));
        });
    }

    public function syncLoan(Loan $loan): void
    {
        $paidPayments = $loan->payments()->with('loan')->where('status', 'paid')->get();

        $paidInstallments = $paidPayments->count();
        $paidAmount = (float) $paidPayments->sum('amount');

        $remainingAmount = max(
            0,
            round(((float) $loan->total_amount - $paidAmount), 2)
        );

        $status = $remainingAmount <= 0
            ? 'completed'
            : ($loan->status === 'defaulted' ? 'defaulted' : 'active');

        $loan->update([
            'paid_installments' => $paidInstallments,
            'remaining_amount' => $remainingAmount,
            'status' => $status,
        ]);
    }
}
