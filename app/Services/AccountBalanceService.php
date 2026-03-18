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
    }

    public function handleDeleted(Transaction $transaction): void
    {
        $transaction->account->decrement('balance', $transaction->amount);
    }
}
