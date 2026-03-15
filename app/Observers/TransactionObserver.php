<?php

namespace App\Observers;

use App\Models\Transaction;

class TransactionObserver
{

    /**
     * Handle the Transaction "created" event.
     */

    public function created(Transaction $transaction): void
    {
        \Log::info('Observer created fired', [
            'transaction_id' => $transaction->id,
            'amount'         => $transaction->amount,
            'account_id'     => $transaction->account_id,
            'account'        => $transaction->account?->name,
        ]);

        $transaction->account->increment('balance', $transaction->amount);
    }

    public function updated(Transaction $transaction): void
    {
        // Reverti il vecchio valore, applica il nuovo
        $diff = $transaction->amount - $transaction->getOriginal('amount');
        $transaction->account->increment('balance', $diff);

        // ✅ Se è un transfer, aggiorna anche il conto destinazione
        if ($transaction->to_account_id) {
            $transaction->toAccount->increment('balance', abs($diff));
        }
    }

    public function deleted(Transaction $transaction): void
    {
        $transaction->account->decrement('balance', $transaction->amount);
        // ✅ Se è un transfer, aggiorna anche il conto destinazione
        if ($transaction->to_account_id) {
            $transaction->toAccount->decrement('balance', abs($transaction->amount));
        }
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
