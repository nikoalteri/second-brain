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
     * Intra-request only: no Octane/Swoole is in use, so this never persists across HTTP
     * requests. Residual-state and reentrancy behavior is asserted by
     * tests/Unit/Observers/ObserverStaticStateTest.php (Phase 18, D-02: measured as
     * lower severity — a failed mid-update write can leave a residual entry, but the
     * subsequent legitimate update still produces the correct cycle status and card
     * balance because CreditCardCycleService::syncCycleAndCardFromPayment() recomputes
     * the balance authoritatively rather than applying the stale previousStatus as a
     * delta; measured residue: {"<id>":"pending"}).
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
