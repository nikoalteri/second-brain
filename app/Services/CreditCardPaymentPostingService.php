<?php

namespace App\Services;

use App\Enums\CreditCardPaymentStatus;
use App\Models\CreditCardPayment;
use App\Models\Transaction;
use App\Models\TransactionType;
use Illuminate\Support\Facades\DB;

class CreditCardPaymentPostingService
{
    public function syncPosting(CreditCardPayment $payment): void
    {
        $payment->loadMissing('creditCard');

        if (! $payment->creditCard) {
            return;
        }

        DB::transaction(function () use ($payment) {
            if ($payment->status === CreditCardPaymentStatus::PAID) {
                $this->upsertPostingTransaction($payment);
                return;
            }

            $this->removePostingTransaction($payment);
        });
    }

    public function deletePosting(CreditCardPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $this->removePostingTransaction($payment);
        });
    }

    private function upsertPostingTransaction(CreditCardPayment $payment): void
    {
        $typeId = TransactionType::query()->firstOrCreate(
            ['name' => 'Credit Card payment'],
            ['is_income' => false]
        )->id;

        $date = $payment->actual_date ?? $payment->due_date;

        if (! $date) {
            return;
        }

        $amount = -abs((float) $payment->total_amount);

        $payload = [
            'user_id' => $payment->creditCard->user_id,
            'account_id' => $payment->creditCard->account_id,
            'transaction_type_id' => $typeId,
            'transaction_category_id' => null,
            'description' => 'Credit Card payment - ' . $payment->creditCard->name,
            'amount' => $amount,
            'date' => $date,
            'competence_month' => $date->format('Y-m'),
            'notes' => $payment->notes,
            'is_transfer' => false,
            'transfer_pair_id' => null,
            'transfer_direction' => null,
        ];

        $existing = Transaction::withTrashed()
            ->where('credit_card_payment_id', $payment->id)
            ->first();

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->fill($payload)->save();

            CreditCardPayment::withoutEvents(function () use ($payment, $existing) {
                $payment->updateQuietly(['transaction_id' => $existing->id]);
            });

            return;
        }

        $created = Transaction::create([
            'credit_card_payment_id' => $payment->id,
            ...$payload,
        ]);

        CreditCardPayment::withoutEvents(function () use ($payment, $created) {
            $payment->updateQuietly(['transaction_id' => $created->id]);
        });
    }

    private function removePostingTransaction(CreditCardPayment $payment): void
    {
        $posting = Transaction::query()
            ->where('credit_card_payment_id', $payment->id)
            ->first();

        if ($posting) {
            $posting->delete();
        }

        CreditCardPayment::withoutEvents(function () use ($payment) {
            $payment->updateQuietly(['transaction_id' => null]);
        });
    }
}
