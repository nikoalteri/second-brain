<?php

namespace App\Services;

use App\Enums\NotificationSeverity;
use App\Models\Notification;
use App\Models\Subscription;
use App\Services\Notifications\ConditionSpec;
use App\Services\Notifications\NotificationConditionPublisher;
use Carbon\CarbonInterface;

/**
 * Flags a recorded renewal charge that is higher than the immediately preceding one.
 *
 * Event-based: it runs once for a freshly posted renewal and is deliberately not a
 * FinanceNotificationEvaluator trigger, so a later reconcile never resolves it. Amounts are the
 * recorded automatic renewal amounts, never claimed as bank-confirmed charges.
 */
class SubscriptionPriceHikeDetector
{
    public const TRIGGER = 'subscription_price_hike';

    public function __construct(
        private readonly SubscriptionRenewalHistory $history,
        private readonly NotificationConditionPublisher $publisher,
    ) {}

    public function detect(Subscription $subscription, CarbonInterface $renewalDate): ?Notification
    {
        [$current, $previous] = array_pad($this->history->latest($subscription, 2), 2, null);

        if ($current === null || $previous === null) {
            return null;
        }

        // Only the charge that was just posted can raise an alert; an older back-filled date never does.
        if ($current['date'] !== $renewalDate->toDateString() || $current['ambiguous'] || $previous['ambiguous']) {
            return null;
        }

        // Compare like with like. An incompatible or legacy predecessor means "unknown", and an older
        // comparable charge is never substituted for it.
        if ($current['frequency'] === null || $current['currency'] === null
            || $current['frequency'] !== $previous['frequency']
            || $current['currency'] !== $previous['currency']) {
            return null;
        }

        $delta = $current['amount_minor'] - $previous['amount_minor'];

        if ($delta <= 0) {
            return null;
        }

        $currency = $current['currency'];

        return $this->publisher->publish($subscription->user_id, new ConditionSpec(
            self::TRIGGER,
            'subscription',
            $subscription->id,
            $current['date'],
            NotificationSeverity::WARNING,
            'Price increase: '.$subscription->name,
            sprintf(
                'The recorded automatic renewal of %s went from %s %s to %s %s (+%s %s). This is the amount recorded by the renewal, not a bank-confirmed charge.',
                $subscription->name,
                $currency,
                $previous['amount'],
                $currency,
                $current['amount'],
                $currency,
                number_format($delta / 100, 2, '.', ''),
            ),
            '/subscriptions',
        ));
    }
}
