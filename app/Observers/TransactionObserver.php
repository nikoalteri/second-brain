<?php

namespace App\Observers;

use App\Enums\CreditCardPaymentStatus;
use App\Models\CreditCardPayment;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    public function created(Transaction $transaction): void
    {
        Log::info('Transaction created', ['transaction_id' => $transaction->id]);
        app(\App\Services\AccountBalanceService::class)
            ->handleCreated($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        app(\App\Services\AccountBalanceService::class)
            ->handleUpdated($transaction);

        $this->syncTransferPair($transaction);
    }

    public function deleted(Transaction $transaction): void
    {
        app(\App\Services\AccountBalanceService::class)
            ->handleDeleted($transaction);

        if ($transaction->credit_card_payment_id) {
            $payment = CreditCardPayment::query()->find($transaction->credit_card_payment_id);

            if ($payment) {
                $payload = [
                    'transaction_id' => null,
                ];

                if ($payment->status === CreditCardPaymentStatus::PAID) {
                    $payload['status'] = CreditCardPaymentStatus::PENDING;
                    $payload['actual_date'] = null;
                }

                $payment->update($payload);
            }
        }

        // A transfer is one operation: deleting either leg deletes the other. The pair lookup
        // skips trashed rows, so the leg deleted by this cascade does not recurse.
        $this->pairedLeg($transaction)?->delete();
    }

    public function restored(Transaction $transaction): void
    {
        app(\App\Services\AccountBalanceService::class)
            ->handleCreated($transaction);

        // Restoring either leg restores the other, mirroring the delete cascade. Once the
        // pair is restored it is no longer trashed, so this does not recurse.
        $this->pairedLeg($transaction, trashed: true)?->restore();

        if ($transaction->credit_card_payment_id) {
            $payment = CreditCardPayment::query()->find($transaction->credit_card_payment_id);

            if ($payment) {
                $payment->update([
                    'transaction_id' => $transaction->id,
                    'status' => CreditCardPaymentStatus::PAID,
                    'actual_date' => $payment->actual_date ?? $transaction->date?->toDateString(),
                ]);
            }
        }
    }

    /**
     * The other leg of a transfer, or null for non-transfers and unpaired legacy rows.
     */
    private function pairedLeg(Transaction $transaction, bool $trashed = false): ?Transaction
    {
        if (! $transaction->transfer_pair_id) {
            return null;
        }

        $query = $trashed ? Transaction::onlyTrashed() : Transaction::query();

        return $query
            ->where('transfer_pair_id', $transaction->transfer_pair_id)
            ->where('id', '!=', $transaction->id)
            ->first();
    }

    /**
     * Mirrors the fields that define a transfer (amount, date, accounts) onto the other leg,
     * so the two legs never disagree. The pair is saved with events, which adjusts its
     * account balance; the pair's own sync then finds nothing left to change.
     */
    private function syncTransferPair(Transaction $transaction): void
    {
        $pair = $this->pairedLeg($transaction);

        if ($pair === null) {
            return;
        }

        if ($transaction->wasChanged('amount')) {
            $amount = abs((float) $transaction->amount);
            $pair->amount = $pair->transfer_direction === 'in' ? $amount : -$amount;
        }

        foreach (['date', 'competence_month'] as $field) {
            if ($transaction->wasChanged($field)) {
                $pair->{$field} = $transaction->{$field};
            }
        }

        // The OUT leg records its destination; the IN leg sits on that account.
        if ($transaction->transfer_direction === 'out' && $transaction->wasChanged('to_account_id')) {
            $pair->account_id = $transaction->to_account_id;
        }

        if ($transaction->transfer_direction === 'in' && $transaction->wasChanged('account_id')) {
            $pair->to_account_id = $transaction->account_id;
        }

        if ($pair->isDirty()) {
            $pair->save();
        }
    }

    public function forceDeleted(Transaction $transaction): void
    {
        //
    }
}
