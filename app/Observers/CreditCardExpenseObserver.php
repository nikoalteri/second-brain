<?php

namespace App\Observers;

use App\Models\CreditCardExpense;
use App\Services\CreditCardExpenseService;

class CreditCardExpenseObserver
{
    /**
     * Track original pointers between updating/updated events.
     *
     * @var array<int, array{card_id:int|null,cycle_id:int|null,amount:float|null}>
     */
    private static array $originalPointers = [];

    public function creating(CreditCardExpense $expense): void
    {
        app(CreditCardExpenseService::class)->validateExpenseChange($expense);
    }

    public function updating(CreditCardExpense $expense): void
    {
        app(CreditCardExpenseService::class)->validateExpenseChange(
            $expense,
            (int) $expense->getOriginal('credit_card_id'),
            (int) $expense->getOriginal('credit_card_cycle_id'),
            (float) $expense->getOriginal('amount')
        );

        self::$originalPointers[$expense->id] = [
            'card_id' => $expense->getOriginal('credit_card_id'),
            'cycle_id' => $expense->getOriginal('credit_card_cycle_id'),
            'amount' => (float) $expense->getOriginal('amount'),
        ];
    }

    public function created(CreditCardExpense $expense): void
    {
        app(CreditCardExpenseService::class)->syncExpense($expense);
    }

    public function updated(CreditCardExpense $expense): void
    {
        $pointers = self::$originalPointers[$expense->id] ?? ['card_id' => null, 'cycle_id' => null, 'amount' => null];
        unset(self::$originalPointers[$expense->id]);

        app(CreditCardExpenseService::class)->syncExpense(
            $expense,
            $pointers['card_id'],
            $pointers['cycle_id'],
            $pointers['amount']
        );
    }

    public function deleted(CreditCardExpense $expense): void
    {
        app(CreditCardExpenseService::class)->removeExpense($expense);
    }

    public function deleting(CreditCardExpense $expense): void
    {
        app(CreditCardExpenseService::class)->validateExpenseRemoval($expense);
    }
}
