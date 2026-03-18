<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Transaction;

class AccountBalanceService
{
    public function handleCreated(Transaction $transaction): void
    {
        $transaction->account->increment('balance', $transaction->amount);
    }

    public function handleUpdated(Transaction $transaction): void
    {
        $oldAccountId = (int) $transaction->getOriginal('account_id');
        $newAccountId = (int) $transaction->account_id;
        $oldAmount    = (float) $transaction->getOriginal('amount');
        $newAmount    = (float) $transaction->amount;

        if ($oldAccountId !== $newAccountId) {
            // Conto cambiato: inverti il vecchio importo sul vecchio conto, applica il nuovo sul nuovo
            Account::find($oldAccountId)?->decrement('balance', $oldAmount);
            $transaction->account->increment('balance', $newAmount);
        } else {
            // Stesso conto: applica solo la differenza
            $diff = $newAmount - $oldAmount;
            $transaction->account->increment('balance', $diff);
        }
    }

    public function handleDeleted(Transaction $transaction): void
    {
        $transaction->account->decrement('balance', $transaction->amount);
    }
}
