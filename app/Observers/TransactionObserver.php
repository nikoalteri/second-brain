<?php

namespace App\Observers;

use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class TransactionObserver
{
    // Previene il loop infinito durante la cancellazione della transazione paired
    private static bool $isDeletingPair = false;

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

        // Se è un trasferimento OUT, soft-delete anche la transazione IN paired
        if (
            ! self::$isDeletingPair
            && $transaction->transfer_pair_id
            && $transaction->transfer_direction !== 'in'
        ) {
            $pair = Transaction::where('transfer_pair_id', $transaction->transfer_pair_id)
                ->where('id', '!=', $transaction->id)
                ->first();

            if ($pair) {
                self::$isDeletingPair = true;
                $pair->delete();
                self::$isDeletingPair = false;
            }
        }
    }

    public function restored(Transaction $transaction): void
    {
        //
    }

    public function forceDeleted(Transaction $transaction): void
    {
        //
    }
}
