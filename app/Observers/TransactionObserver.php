<?php

namespace App\Observers;

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
    }

    public function forceDeleted(Transaction $transaction): void
    {
        //
    }
}
