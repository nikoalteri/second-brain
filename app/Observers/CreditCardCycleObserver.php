<?php

namespace App\Observers;

use App\Enums\CreditCardPaymentStatus;
use App\Models\CreditCardCycle;
use App\Models\CreditCardPayment;
use Illuminate\Support\Facades\DB;

class CreditCardCycleObserver
{
    /**
     * When cycle status changes to PAID, create a payment record if it doesn't exist.
     * This ensures the balance is properly reduced when marking a cycle as paid.
     */
    public function updated(CreditCardCycle $cycle): void
    {
        $originalStatus = $cycle->getOriginal('status');

        // Only process status changes to PAID
        if ($cycle->status->value !== 'paid' || $originalStatus === 'paid') {
            return;
        }

        DB::transaction(function () use ($cycle) {
            // Calculate unpaid amount
            $unpaidAmount = (float) $cycle->total_due - (float) $cycle->paid_amount;

            if ($unpaidAmount <= 0) {
                return; // Nothing to pay
            }

            // Check if payment already exists
            $existingPayment = CreditCardPayment::query()
                ->where('credit_card_cycle_id', $cycle->id)
                ->where('status', CreditCardPaymentStatus::PAID)
                ->first();

            if ($existingPayment) {
                return; // Payment already recorded
            }

            // Create payment record
            CreditCardPayment::create([
                'credit_card_id' => $cycle->credit_card_id,
                'credit_card_cycle_id' => $cycle->id,
                'principal_amount' => $unpaidAmount,
                'total_amount' => $unpaidAmount,
                'status' => CreditCardPaymentStatus::PAID,
            ]);
        });
    }
}
