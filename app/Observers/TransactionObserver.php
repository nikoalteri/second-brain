<?php

namespace App\Observers;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{

    /**
     * Handle the Transaction "created" event.
     */

    public function created(Transaction $transaction): void
    {
        Log::info('Observer created fired', [
            'transaction_id' => $transaction->id,
            'amount'         => $transaction->amount,
            'account_id'     => $transaction->account_id,
            'account'        => $transaction->account?->name,
        ]);
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
    }


    /**
     * Handle the Transaction "restored" event.
     */
    public function restored(Transaction $transaction): void
    {
        //
    }

    /**
     * Handle the Transaction "force deleted" event.
     */
    public function forceDeleted(Transaction $transaction): void
    {
        //
    }
}
