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

        // Se è un trasferimento OUT, soft-delete anche la transazione IN paired.
        // La condizione transfer_direction !== 'in' previene la ricorsione senza flag statici.
        if (
            $transaction->transfer_pair_id
            && $transaction->transfer_direction !== 'in'
        ) {
            $pair = Transaction::where('transfer_pair_id', $transaction->transfer_pair_id)
                ->where('id', '!=', $transaction->id)
                ->first();

            $pair?->delete();
        }
    }

    public function restored(Transaction $transaction): void
    {
        app(\App\Services\AccountBalanceService::class)
            ->handleCreated($transaction);

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

    public function forceDeleted(Transaction $transaction): void
    {
        //
    }
}
