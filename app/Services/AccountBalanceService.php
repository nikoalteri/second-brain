<?php

namespace App\Services;

use App\Models\Transaction;

class AccountBalanceService
{
    public function handleCreated(Transaction $transaction): void
    {
        $transaction->account->increment('balance', $transaction->amount);
    }

    public function handleUpdated(Transaction $transaction): void
    {
        $diff = $transaction->amount - $transaction->getOriginal('amount');
        $transaction->account->increment('balance', $diff);
        if ($transaction->to_account_id) {
            $transaction->toAccount->increment('balance', abs($diff));
        }
    }

    public function handleDeleted(Transaction $transaction): void
    {
        $transaction->account->decrement('balance', $transaction->amount);
        if ($transaction->to_account_id) {
            $transaction->toAccount->decrement('balance', abs($transaction->amount));
        }
    }
}
