<?php

namespace App\Services;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardCycleStatus;
use App\Enums\CreditCardType;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\CreditCardCycle;
use App\Support\Concerns\HasWorkdayCalculation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CreditCardCycleService
{
    use HasWorkdayCalculation;

    private RevolvingCreditCalculator $calculator;
    private CreditCardBalanceService $balanceService;

    public function __construct(
        ?RevolvingCreditCalculator $calculator = null,
        ?CreditCardBalanceService $balanceService = null
    ) {
        $this->calculator = $calculator ?? app(RevolvingCreditCalculator::class);
        $this->balanceService = $balanceService ?? app(CreditCardBalanceService::class);
    }

    /**
     * @deprecated Use RevolvingCreditCalculator directly
     * Kept for backward compatibility with existing tests
     */
    public function calculateRevolvingPaymentBreakdown(CreditCard $card, float $currentBalance): array
    {
        $maxInstallment = (float) ($card->fixed_payment ?? 0);
        $rate = (float) ($card->interest_rate ?? 0);
        $stampDuty = (float) ($card->stamp_duty_amount ?? 0);

        if ($card->type !== CreditCardType::REVOLVING) {
            return [
                'interest_amount' => 0.0,
                'principal_amount' => 0.0,
                'stamp_duty_amount' => round($stampDuty, 2),
                'installment_amount' => round($maxInstallment, 2),
                'total_due' => round($maxInstallment + $stampDuty, 2),
                'next_balance' => round(max(0.0, $currentBalance), 2),
            ];
        }

        if ($maxInstallment <= 0) {
            return [
                'interest_amount' => 0.0,
                'principal_amount' => 0.0,
                'stamp_duty_amount' => round($stampDuty, 2),
                'installment_amount' => 0.0,
                'total_due' => round($stampDuty, 2),
                'next_balance' => round(max(0.0, $currentBalance), 2),
                'invalid_installment' => true,
            ];
        }

        if ($currentBalance <= 0) {
            return [
                'interest_amount' => 0.0,
                'principal_amount' => 0.0,
                'stamp_duty_amount' => round($stampDuty, 2),
                'installment_amount' => 0.0,
                'total_due' => round($stampDuty, 2),
                'next_balance' => round(max(0.0, $currentBalance), 2),
            ];
        }

        // Direct monthly application: 14% annual = 14% monthly per user's requirement
        $monthlyRate = $rate / 100;
        $interestAmount = $monthlyRate > 0 ? round($currentBalance * $monthlyRate, 2) : 0.0;
        $effectiveInstallment = min($maxInstallment, $currentBalance + $interestAmount);
        $principalAmount = round(min($currentBalance, max(0.0, $effectiveInstallment - $interestAmount)), 2);
        $nextBalance = round(max(0.0, $currentBalance - $principalAmount), 2);

        return [
            'interest_amount' => $interestAmount,
            'principal_amount' => $principalAmount,
            'stamp_duty_amount' => round($stampDuty, 2),
            'installment_amount' => round($effectiveInstallment, 2),
            'total_due' => round($effectiveInstallment + $stampDuty, 2),
            'next_balance' => $nextBalance,
            'invalid_installment' => $principalAmount <= 0,
        ];
    }

    public function issueCycle(CreditCardCycle $cycle): bool
    {
        $cycle->loadMissing('creditCard');

        if (! $cycle->creditCard || $cycle->status !== CreditCardCycleStatus::OPEN) {
            return false;
        }

        return DB::transaction(function () use ($cycle) {
            $card = $cycle->creditCard;

            if ($card->type === CreditCardType::CHARGE) {
                // CHARGE card: no interest, full amount due
                $breakdown = $this->calculator->calculateChargePaymentBreakdown($cycle);
            } else {
                // REVOLVING card: interest + principal, fixed payment
                $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);

                if (empty($breakdown)) {
                    return false;
                }
            }

            $cycle->update([
                'interest_amount' => $breakdown['interest_amount'],
                'principal_amount' => $breakdown['principal_amount'],
                'stamp_duty_amount' => $breakdown['stamp_duty_amount'],
                'total_due' => $breakdown['total_due'],
                'status' => CreditCardCycleStatus::ISSUED,
            ]);

            $cycle->payments()->create([
                'credit_card_id' => $card->id,
                'credit_card_cycle_id' => $cycle->id,
                'due_date' => $cycle->due_date,
                'installment_amount' => $breakdown['installment_amount'],
                'interest_amount' => $breakdown['interest_amount'],
                'principal_amount' => $breakdown['principal_amount'],
                'stamp_duty_amount' => $breakdown['stamp_duty_amount'],
                'total_amount' => $breakdown['total_due'],
                'status' => CreditCardPaymentStatus::PENDING,
            ]);

            $this->syncCardBalance($card->fresh(['cycles.payments', 'payments']));

            return true;
        });
    }

    public function ensureCurrentMonthCycle(CreditCard $card, ?Carbon $referenceDate = null): CreditCardCycle
    {
        $referenceDate ??= now();
        $periodMonth = $referenceDate->format('Y-m');
        $periodStartDate = $referenceDate->copy()->startOfMonth();

        $statementDate = $referenceDate->copy()->day(
            min((int) $card->statement_day, $referenceDate->daysInMonth)
        );

        $dueDate = null;
        if (! empty($card->due_day)) {
            $dueMonth = $statementDate->copy()->addMonthNoOverflow();
            $dueDate = $dueMonth->copy()->day(min((int) $card->due_day, $dueMonth->daysInMonth));
            $dueDate = $this->adjustToWorkday($dueDate, (bool) $card->skip_weekends);
        }

        return CreditCardCycle::query()->firstOrCreate(
            [
                'credit_card_id' => $card->id,
                'period_month' => $periodMonth,
                'period_start_date' => $periodStartDate,
                'statement_date' => $statementDate,
            ],
            [
                'period_start_date' => $periodStartDate,
                'statement_date' => $statementDate,
                'due_date' => $dueDate,
                'status' => CreditCardCycleStatus::OPEN,
                'total_spent' => 0,
                'interest_amount' => 0,
                'principal_amount' => 0,
                'stamp_duty_amount' => 0,
                'total_due' => 0,
                'paid_amount' => 0,
            ]
        );
    }

    public function syncCycleAndCardFromPayment(
        int $paymentId,
        ?string $previousStatus = null,
        ?string $currentStatus = null
    ): void {
        $payment = CreditCardPayment::query()->with(['cycle', 'creditCard', 'creditCard.cycles.payments', 'creditCard.payments'])->find($paymentId);

        if (! $payment || ! $payment->creditCard) {
            return;
        }

        DB::transaction(function () use ($payment, $previousStatus, $currentStatus) {
            if ($payment->cycle) {
                $paidAmount = (float) $payment->cycle->payments()
                    ->where('status', CreditCardPaymentStatus::PAID)
                    ->sum('total_amount');

                $status = CreditCardCycleStatus::ISSUED;
                if ($payment->cycle->total_due > 0 && $paidAmount >= (float) $payment->cycle->total_due) {
                    $status = CreditCardCycleStatus::PAID;
                } elseif (
                    $payment->cycle->status !== CreditCardCycleStatus::OPEN
                    && $payment->cycle->due_date
                    && now()->toDateString() > $payment->cycle->due_date->toDateString()
                    && $paidAmount < (float) $payment->cycle->total_due
                ) {
                    $status = CreditCardCycleStatus::OVERDUE;
                }

                $payment->cycle->update([
                    'paid_amount' => round($paidAmount, 2),
                    'status' => $status,
                ]);
            }

            $card = $payment->creditCard->fresh(['cycles.payments', 'payments']);

            $fromPaid = $previousStatus === CreditCardPaymentStatus::PAID->value;
            $toPaid = $currentStatus === CreditCardPaymentStatus::PAID->value;
            $principal = (float) $payment->principal_amount;

            if (! $fromPaid && $toPaid) {
                // Payment marked as PAID: reduce debt by principal
                $this->balanceService->applyPrincipalPayment($card, $principal);
            } elseif ($fromPaid && ! $toPaid) {
                // Payment unmarked: restore debt
                $this->balanceService->reversePrincipalPayment($card, $principal);
            }

            $this->syncCardBalance($card->fresh());
        });
    }

    public function syncCardById(int $cardId): void
    {
        $card = CreditCard::query()->with(['cycles.payments', 'payments'])->find($cardId);

        if (! $card) {
            return;
        }

        $this->syncCardBalance($card);
    }

    public function handleDeletedPayment(CreditCardPayment $payment): void
    {
        $card = CreditCard::query()->with(['cycles.payments', 'payments'])->find((int) $payment->credit_card_id);

        if (! $card) {
            return;
        }

        DB::transaction(function () use ($payment, $card) {
            if ($payment->credit_card_cycle_id) {
                $cycle = CreditCardCycle::query()->find($payment->credit_card_cycle_id);

                if ($cycle) {
                    $paidAmount = (float) $cycle->payments()
                        ->where('status', CreditCardPaymentStatus::PAID)
                        ->sum('total_amount');

                    $status = $cycle->status;
                    if ($cycle->total_due > 0 && $paidAmount >= (float) $cycle->total_due) {
                        $status = CreditCardCycleStatus::PAID;
                    } elseif (
                        $cycle->status !== CreditCardCycleStatus::OPEN
                        && $cycle->due_date
                        && now()->toDateString() > $cycle->due_date->toDateString()
                        && $paidAmount < (float) $cycle->total_due
                    ) {
                        $status = CreditCardCycleStatus::OVERDUE;
                    } elseif ($cycle->status !== CreditCardCycleStatus::OPEN) {
                        $status = CreditCardCycleStatus::ISSUED;
                    }

                    $cycle->update([
                        'paid_amount' => round($paidAmount, 2),
                        'status' => $status,
                    ]);
                }
            }

            if ($payment->status === CreditCardPaymentStatus::PAID) {
                // Payment was marked PAID: restore debt on deletion
                $this->balanceService->reversePrincipalPayment($card, (float) $payment->principal_amount);
            }

            $this->syncCardBalance($card->fresh());
        });
    }

    public function syncCardBalance(CreditCard $card): void
    {
        $card->update([
            'current_balance' => round(max(0.0, (float) $card->current_balance), 2),
        ]);
    }

    public function refreshCycleStatuses(CreditCard $card): void
    {
        $card->loadMissing('cycles.payments');

        foreach ($card->cycles as $cycle) {
            if ($cycle->status === CreditCardCycleStatus::OPEN) {
                continue;
            }

            $paidAmount = (float) $cycle->payments
                ->where('status', CreditCardPaymentStatus::PAID)
                ->sum('total_amount');

            $status = CreditCardCycleStatus::ISSUED;

            if ((float) $cycle->total_due > 0 && $paidAmount >= (float) $cycle->total_due) {
                $status = CreditCardCycleStatus::PAID;
            } elseif ($cycle->due_date && now()->toDateString() > $cycle->due_date->toDateString()) {
                $status = CreditCardCycleStatus::OVERDUE;
            }

            if ($cycle->status !== $status || (float) $cycle->paid_amount !== round($paidAmount, 2)) {
                $cycle->update([
                    'paid_amount' => round($paidAmount, 2),
                    'status' => $status,
                ]);
            }
        }
    }
}
