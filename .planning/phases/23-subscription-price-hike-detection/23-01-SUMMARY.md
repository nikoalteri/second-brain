# Plan 23-01 Summary — reliable renewal-charge history

## Delivered
- Migration `2026_10_18_090000`: nullable `renewal_frequency` / `renewal_currency` snapshots on `transactions` and `credit_card_expenses`. Legacy rows stay NULL (never assumed comparable).
- Snapshots set at creation: frequency slug from the subscription; currency from the billing account, or the card's settlement account (cards have no currency of their own).
- Upsert paths report inserted vs existing. A retry keeps the original amount and snapshots and moves no balance. Soft-deleted same-date account postings are restored, not re-alerted.
- Cross-table guard: a renewal already posted on the other path (source switched between runs) is not posted again. The subscription is reloaded after the lock so the path is decided from the committed row.
- `next_renewal_date` is no longer rewound when an older renewal is repeated.
- `SubscriptionRenewalHistory::latest()`: newest distinct renewal dates across both paths, owner-scoped, soft-deleted charges excluded, same-date cross-table rows flagged `ambiguous`.

## Tests
`SubscriptionRenewalHistoryTest` (account/card inserts, catch-up after a missed day, retry with changed price, restore, source switch both ways, legacy rows, ambiguity, owner scoping, deleted charges, date rewind). Full suite: 614 tests, only `ExampleTest` fails (needs `npm run build`).

## UAT finding
Not performed. The read-only check of the missing renewals on UAT is manual and was explicitly skipped for this delivery; local tests do not prove the UAT scheduler ran. Track it separately.

## Limits
Concurrency is only structurally tested on SQLite (`FOR UPDATE` is ignored); the MySQL two-connection race is unverified.
