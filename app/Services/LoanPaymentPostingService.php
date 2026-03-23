<?php

namespace App\Services;

use App\Enums\LoanPaymentStatus;
use App\Models\LoanPayment;
use App\Models\Transaction;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

class LoanPaymentPostingService
{
    public function syncPosting(LoanPayment $payment): void
    {
        $payment->loadMissing('loan');

        if (! $payment->loan) {
            return;
        }

        DB::transaction(function () use ($payment) {
            if ($payment->status === LoanPaymentStatus::PAID) {
                $this->upsertPostingTransaction($payment);
                return;
            }

            $this->removePostingTransaction($payment);
        });
    }

    public function deletePosting(LoanPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->removePostingTransaction($payment);
        });
    }

    private function upsertPostingTransaction(LoanPayment $payment): void
    {
        $typeId = TransactionType::query()->firstOrCreate(
            ['name' => 'Loan installment payment'],
            ['is_income' => false]
        )->id;

        $amount = -abs((float) $payment->amount);
        $date = $payment->actual_date ?? $payment->due_date;

        if (! $date) {
            return;
        }

        $payload = [
            'user_id' => $payment->loan->user_id,
            'account_id' => $payment->loan->account_id,
            'transaction_type_id' => $typeId,
            'transaction_category_id' => null,
            'description' => 'Loan installment payment - ' . $payment->loan->name,
            'amount' => $amount,
            'date' => $date,
            'competence_month' => $date->format('Y-m'),
            'notes' => $payment->notes,
            'is_transfer' => false,
            'transfer_pair_id' => null,
            'transfer_direction' => null,
        ];

        $existing = Transaction::withTrashed()
            ->where('loan_payment_id', $payment->id)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->fill($payload)->save();
            return;
        }

        Transaction::create([
            'loan_payment_id' => $payment->id,
            ...$payload,
        ]);
    }

    private function removePostingTransaction(LoanPayment $payment): void
    {
        $posting = Transaction::query()
            ->where('loan_payment_id', $payment->id)
            ->first();

        if ($posting) {
            $posting->delete();
        }
    }
}
