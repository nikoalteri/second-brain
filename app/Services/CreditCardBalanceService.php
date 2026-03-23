<?php

namespace App\Services;

use App\Models\CreditCard;
use Illuminate\Validation\ValidationException;

class CreditCardBalanceService
{
    /**
     * Add an expense to the card balance
     * 
     * Increases current_balance by the expense amount
     * Returns updated card balance
     * 
     * @param CreditCard $card
     * @param float $amount Expense amount (positive)
     * @return float New current balance
     * 
     * @throws ValidationException If credit limit would be exceeded
     */
    public function addExpense(CreditCard $card, float $amount): float
    {
        if ($amount <= 0) {
            return (float) $card->current_balance;
        }

        $this->validateCreditLimit($card, $amount);

        $newBalance = round(
            max(0.0, (float) $card->current_balance + $amount),
            2
        );

        $card->update(['current_balance' => $newBalance]);

        return $newBalance;
    }

    /**
     * Remove an expense from card balance
     * 
     * Decreases current_balance by the expense amount
     * Returns updated card balance
     * 
     * @param CreditCard $card
     * @param float $amount Expense amount to remove (positive)
     * @return float New current balance
     */
    public function removeExpense(CreditCard $card, float $amount): float
    {
        if ($amount <= 0) {
            return (float) $card->current_balance;
        }

        $newBalance = round(
            max(0.0, (float) $card->current_balance - $amount),
            2
        );

        $card->update(['current_balance' => $newBalance]);

        return $newBalance;
    }

    /**
     * Apply a principal payment (reduces debt)
     * 
     * Decreases current_balance by the principal amount
     * Returns updated card balance
     * 
     * @param CreditCard $card
     * @param float $principalAmount Amount paid toward principal (positive)
     * @return float New current balance
     */
    public function applyPrincipalPayment(CreditCard $card, float $principalAmount): float
    {
        if ($principalAmount <= 0) {
            return (float) $card->current_balance;
        }

        $newBalance = round(
            max(0.0, (float) $card->current_balance - $principalAmount),
            2
        );

        $card->update(['current_balance' => $newBalance]);

        return $newBalance;
    }

    /**
     * Reverse a principal payment (increases debt)
     * 
     * Increases current_balance by the principal amount
     * Returns updated card balance
     * 
     * @param CreditCard $card
     * @param float $principalAmount Amount to reverse (positive)
     * @return float New current balance
     */
    public function reversePrincipalPayment(CreditCard $card, float $principalAmount): float
    {
        if ($principalAmount <= 0) {
            return (float) $card->current_balance;
        }

        $newBalance = round(
            max(0.0, (float) $card->current_balance + $principalAmount),
            2
        );

        $card->update(['current_balance' => $newBalance]);

        return $newBalance;
    }

    /**
     * Get current debt on card
     * 
     * @param CreditCard $card
     * @return float Current debt (current_balance)
     */
    public function getCurrentDebt(CreditCard $card): float
    {
        $card->refresh();
        return (float) $card->current_balance;
    }

    /**
     * Get available credit remaining
     * 
     * Returns null for unlimited cards
     * 
     * @param CreditCard $card
     * @return float|null Available credit or null if unlimited
     */
    public function getAvailableCredit(CreditCard $card): ?float
    {
        if ($card->credit_limit === null) {
            return null;
        }

        return max(
            0.0,
            (float) $card->credit_limit - (float) $card->current_balance
        );
    }

    /**
     * Validate that adding an amount won't exceed credit limit
     * 
     * @param CreditCard $card
     * @param float $amount Amount to add
     * 
     * @throws ValidationException If limit would be exceeded
     */
    private function validateCreditLimit(CreditCard $card, float $amount): void
    {
        if ($card->credit_limit === null) {
            // Unlimited card, no validation needed
            return;
        }

        $candidate = round(
            (float) $card->current_balance + $amount,
            2
        );

        if ($candidate > (float) $card->credit_limit) {
            throw ValidationException::withMessages([
                'amount' => 'Credit limit exceeded. Available: ' .
                    $this->getAvailableCredit($card) . ', Requested: ' . $amount,
            ]);
        }
    }
}
