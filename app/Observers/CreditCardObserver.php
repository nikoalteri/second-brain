<?php

namespace App\Observers;

use App\Models\CreditCard;
use App\Services\CreditCardCycleService;

class CreditCardObserver
{
    /**
     * Re-entrancy guard: syncCardBalance() itself calls $card->update(['current_balance' => ...]),
     * which fires this same updated() hook again on the same instance. That nested update never
     * changes opening_balance, so the wasChanged('opening_balance') check alone already prevents
     * infinite recursion — this static flag is an explicit second guard per record ID, so the
     * invariant holds even if syncCardBalance()'s update shape changes later.
     *
     * @var array<int, true>
     */
    private static array $syncing = [];

    public function updated(CreditCard $card): void
    {
        if (isset(self::$syncing[$card->id]) || ! $card->wasChanged('opening_balance')) {
            return;
        }

        self::$syncing[$card->id] = true;

        try {
            app(CreditCardCycleService::class)->syncCardBalance($card);
        } finally {
            unset(self::$syncing[$card->id]);
        }
    }
}
