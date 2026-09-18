# Phase 23: Subscription price-hike detection - Context

**Gathered:** 2026-09-17
**Status:** Ready for planning

<domain>
## Phase Boundary

For subscriptions with `auto_create_transaction = true`, each renewal already creates a `Transaction` (account-billed) or `CreditCardExpense` (card-billed) row linked via `subscription_id` (+ `subscription_renewal_date`) — see `SubscriptionService::upsertTransaction()` / `upsertCreditCardExpense()`. This phase compares each newly-created renewal charge against the immediately preceding one for the same subscription and, when the amount increased, creates a `Notification` (reusing Phase 22's delivery). No new UI, no new delivery channel — this phase is purely detection logic layered on existing renewal data plus Phase 22's notification plumbing.

</domain>

<decisions>
## Implementation Decisions

### Detection method
- **D-01:** Compare real charged amounts, not the subscription's configured `monthly_cost`/`annual_cost`. For each subscription, order its renewal charges (`Transaction` where `subscription_id` matches, or `CreditCardExpense` where `subscription_id` matches, depending on which billing path the subscription uses) by `subscription_renewal_date` and compare the newest charge's amount to the one immediately before it.
- **D-02:** Any increase greater than 0 triggers a notification — no minimum threshold/noise filter.
- **D-03:** Decreases are never notified — this phase only detects and surfaces price *increases*, per its name and goal. No decision needed beyond "not in scope."

### Scope of subscriptions covered
- **D-04:** Only subscriptions with `auto_create_transaction = true` are in scope — these are the only ones with a queryable charge history. Subscriptions without automatic renewal tracking are explicitly excluded from this phase (no data to compare); this is a hard scope boundary, not deferred as a "future improvement" unless the user later asks to track a manually-entered "last known price" for them.

### Integration with Phase 22
- **D-05:** Reuses the `Notification` model and its in-app-only, deduplicated-per-active-condition delivery model established in Phase 22 (`22-CONTEXT.md`) — do not build a separate delivery mechanism. A "price hike" notification for a given subscription+renewal-date pair should only ever be created once (the underlying event — a specific renewal charge — never repeats, so the Phase 22 "one per active condition" dedup concern is naturally satisfied here: the idempotency key is simply "already notified for this subscription_id + this renewal charge id/date").

### The agent's Discretion
- Exact point in the code where the comparison runs — either inside `SubscriptionService::processRenewal()` right after the new renewal charge is persisted (catches it the moment `subscriptions:sync-renewals` runs, 01:55 daily), or as a separate step inside Phase 22's new digest command that scans recently-created renewal charges. Either satisfies the decisions above; pick whichever fits the Phase 22 implementation better.
- Exact `Notification` payload wording/fields (e.g., `related_model`/`related_id` pointing at the Subscription or at the renewal Transaction/CreditCardExpense)
- Whether percentage change is computed/stored/shown in addition to the absolute delta (nice-to-have, not required)

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### This phase's dependency
- `.planning/phases/22-proactive-notifications-wire-the-existing-notification-model-to-real-financial-triggers/22-CONTEXT.md` — notification delivery model and dedup approach this phase reuses; **must be planned/implemented first or alongside**

### Renewal charge data source
- `app/Services/SubscriptionService.php` — `syncDueRenewals()`, `processRenewal()`, `upsertTransaction()`, `upsertCreditCardExpense()`, `getBillingAmount()` — this is where each renewal's actual charge amount is decided and persisted; the comparison logic plugs in here or reads its output
- `app/Models/Transaction.php` — `subscription_id`, `subscription_renewal_date`, `amount` fields for account-billed subscriptions
- `app/Models/CreditCardExpense.php` — same linkage for card-billed subscriptions
- `app/Models/Subscription.php` — `auto_create_transaction` flag gates which subscriptions are in scope

No external specs — requirements fully captured in decisions above.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- Renewal charge history already exists as a byproduct of `syncDueRenewals()` — no new tracking table needed, just a query ordered by `subscription_renewal_date`

### Established Patterns
- `upsertTransaction()`/`upsertCreditCardExpense()` already use `Transaction::withTrashed()->where('subscription_id', ...)->whereDate('subscription_renewal_date', ...)` lookups — the same query shape (minus `whereDate`, ordered instead) gives the comparison history

### Integration Points
- Most natural hook: immediately after a new renewal charge is created in `processRenewal()`, since that's the exact moment "new charge amount" becomes known and the previous one is a simple prior-row lookup

</code_context>

<specifics>
## Specific Ideas

The user's own example during discussion: Netflix renewing at 15.99€ after a prior renewal at 13.99€ should be flagged as a +2.00€ (+14.3%) increase — confirms the "compare consecutive real charges" model, not a configured-cost comparison.

</specifics>

<deferred>
## Deferred Ideas

- Tracking a manually-entered "last known price" for subscriptions without `auto_create_transaction`, to extend price-hike detection to them — explicitly out of this phase's scope, not committed
- Minimum-threshold/noise filtering on the increase amount — user explicitly chose "any increase," but noted as available to revisit if it proves noisy in practice

### Reviewed Todos (not folded)
None — no pending todo backlog was cross-referenced for this ad-hoc phase.

</deferred>

## Audit-derived requirements (added 2026-09-18)

Source: the 2026-09-17 project audit (kept locally under `.planning/audits/2026-09-17/`, not tracked). These are **proposed requirements to settle during `/gsd-plan-phase`**, not decisions already taken; the discuss-phase decisions above stay authoritative unless the maintainer changes them. Foundations already delivered by the audit hardening are listed in ROADMAP.md ("Audit hardening").

- **Do not over-promise what the data knows.** `SubscriptionService` generates the charge from the configured price and never receives the vendor's price, so a configured change is not an independently discovered hike, and the amount is not "actually charged" until a bank/import row confirms it.
- Distinguish three levels: expected (configured), recorded (generated charge) and confirmed (matched to an imported/bank row).
- Compare like with like: same frequency, currency and source. Keep one history across an account -> card change, and make retries neither double a comparison nor a notification.
- A configured price change may be flagged, but not presented as a bank-confirmed increase.

---

*Phase: 23-subscription-price-hike-detection*
*Context gathered: 2026-09-17*
