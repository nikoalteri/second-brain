# Plan 23-02 Summary — detect and notify recorded price increases

## Delivered
- `SubscriptionPriceHikeDetector`: runs inside `processRenewal()`'s transaction only after a genuinely new charge. Compares the new amount with the immediately previous distinct renewal date, only when both snapshots have the same frequency and currency; integer minor units; any increase of >= 1 minor unit qualifies; equal/lower, first renewal, legacy or incompatible predecessor do not (no fallback to an older charge).
- Publishes via `NotificationConditionPublisher` with trigger `subscription_price_hike`, subject `subscription`, period key = renewal date, warning severity, link `/subscriptions`. The text states previous/new/delta as recorded automatic renewal amounts, not bank-confirmed.
- Event-based: not in `FinanceNotificationEvaluator::TRIGGERS`, no retroactive scan, no notification for a configured price edit alone. A publisher failure rolls back the charge, balance and date advance together.

## Tests
`SubscriptionPriceHikeTest` (13.99 -> 15.99, +0.01, equal/decrease, first renewal, legacy, frequency/currency change, no fallback, annual, account<->card switches, retry and second sync, publisher failure then retry, price edit alone).

## UAT / open items
Controlled UAT renewal and bell check not performed (manual, skipped). MySQL two-connection race not verified. ROADMAP/STATE not marked implemented until UAT is done.
