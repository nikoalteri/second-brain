<?php

namespace App\Observers;

use App\Models\CreditCardPayment;
use App\Services\CreditCardCycleService;
use App\Services\CreditCardPaymentPostingService;

class CreditCardPaymentObserver
{
    /**
     * Keep previous statuses between updating/updated events.
     *
     * @var array<int, string|null>
     */
    private static array $previousStatuses = [];

    public function updating(CreditCardPayment $payment): void
    {
        $original = $payment->getOriginal('status');
        self::$previousStatuses[$payment->id] = $original instanceof \BackedEnum
            ? $original->value
            : (is_string($original) ? $original : null);
    }

    public function created(CreditCardPayment $payment): void
    {
        app(CreditCardPaymentPostingService::class)->syncPosting($payment);
        app(CreditCardCycleService::class)->syncCycleAndCardFromPayment(
            $payment->id,
            null,
            $payment->status?->value ?? (string) $payment->status
        );
    }

    public function updated(CreditCardPayment $payment): void
    {
        $previousStatus = self::$previousStatuses[$payment->id] ?? null;
        unset(self::$previousStatuses[$payment->id]);

        app(CreditCardPaymentPostingService::class)->syncPosting($payment);
        app(CreditCardCycleService::class)->syncCycleAndCardFromPayment(
            $payment->id,
            $previousStatus,
            $payment->status?->value ?? (string) $payment->status
        );
    }

    public function deleted(CreditCardPayment $payment): void
    {
        app(CreditCardPaymentPostingService::class)->deletePosting($payment);
        app(CreditCardCycleService::class)->handleDeletedPayment($payment);
    }
}
