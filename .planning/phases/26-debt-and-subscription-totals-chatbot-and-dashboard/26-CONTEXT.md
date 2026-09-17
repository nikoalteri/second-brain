# Phase 26: Debt & subscription totals (chatbot + dashboard) - Context

**Gathered:** 2026-09-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Four new aggregate figures — total monthly-equivalent cost of active subscriptions, total remaining loan principal, total credit-card debt, and their combined "total debts" — exposed on both surfaces the user already uses: the chatbot (new `ChatIntent`s, reusing the Phase 17 `IntentRouter` allow-list pattern) and the SPA Dashboard (new `DashboardController` data, reusing the Phase 24 forecast's precedent of composing existing services rather than duplicating aggregation logic). No new data sources — all four figures are computable from `Subscription`, `Loan`, and `CreditCard` fields that already exist.

</domain>

<decisions>
## Implementation Decisions

### Subscriptions total
- **D-01:** The headline/highlight figure is the **monthly-equivalent normalized total** across active subscriptions (`SubscriptionStatus::ACTIVE`) — reuse `SubscriptionService::calculateMonthlyCost($billingAmount, $frequency)` composed with `getBillingAmount($subscription)`, exactly as `UpcomingPaymentsService` already does for per-item amounts. Do not write new normalization math.
- **D-02:** Each subscription's own line item still shows its **original** billing amount and frequency (e.g., "49.90€/year"), not the normalized figure — only the aggregate headline is normalized. This is "both" per the user's explicit preference: a comparable total, without hiding the real per-subscription billing shape.

### Debt totals
- **D-03:** Three related figures, all in scope together: (a) total remaining loan principal — `SUM(Loan.remaining_amount)` where `status = LoanStatus::ACTIVE`; (b) total credit-card debt — `SUM(CreditCard.current_balance)` where `status = CreditCardStatus::ACTIVE`; (c) combined total debts — (a) + (b). No other debt sources exist in the current data model (negative account balances are explicitly NOT treated as structured debt) — this is a hard scope boundary from the available data, not a preference choice.

### Chatbot surface
- **D-04:** Five new `ChatIntent` implementations, added to `IntentRouter::SUPPORTED_INTENTS`: four single-topic intents (`subscriptions_total`, `loans_remaining_total`, `credit_card_debt_total`, `total_debts`) matching the existing one-intent-per-question pattern (`AccountBalancesIntent` is the reference shape), **plus** a fifth combined `debt_overview` intent that returns all three debt-related figures (loans/cards/combined) — and, per the user's "why not both" preference, also folds in the subscriptions total — in one response, for a single "what's my financial situation" style question. Exact response shape (headline/highlight/items) follows the existing `ChatIntent` contract; `items` for `debt_overview` should be the per-source breakdown (loans / cards / subscriptions), `highlight` the grand total debts figure.

### Dashboard surface
- **D-05:** Same four figures (subscriptions total, loans remaining, credit card debt, combined debts) added to the existing SPA Dashboard via `DashboardController`, alongside Phase 24's cash-flow forecast figure — both are dashboard-level aggregate numbers and should be planned/executed with awareness of each other to avoid two separate ad-hoc "add a number to the dashboard" implementations that diverge in style.

### The agent's Discretion
- Whether the five new chatbot intents live as five separate classes or whether `debt_overview` composes the other four intents' `handle()` output internally (DRY) vs. reimplementing the aggregation directly — either is acceptable as long as the underlying SQL isn't duplicated across files
- Exact `DashboardController` response shape/endpoint (new method vs. extending `charts()`) — same discretion granted to Phase 24, keep both phases' additions consistent with each other
- Currency handling for the totals — reuse `ResolvesUserCurrency` (already used by `AccountBalancesIntent`) for the chatbot side; Dashboard side should follow whatever currency convention `DashboardController` already uses elsewhere

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Chatbot pattern to extend
- `app/Services/Chatbot/Contracts/ChatIntent.php` — interface every new intent implements
- `app/Services/Chatbot/IntentRouter.php` — `SUPPORTED_INTENTS` allow-list; new intent keys must be added here
- `app/Services/Chatbot/Intents/AccountBalancesIntent.php` — reference implementation shape (query → map to `items` → `highlight` → return array)
- `app/Services/Chatbot/Concerns/ResolvesUserCurrency.php` — reuse for currency resolution

### Data sources
- `app/Models/Subscription.php`, `app/Enums/SubscriptionStatus.php` — `monthly_cost`, `annual_cost`, `status`
- `app/Services/SubscriptionService.php` — `calculateMonthlyCost()`, `getBillingAmount()` — reuse, don't reimplement
- `app/Models/Loan.php`, `app/Enums/LoanStatus.php` — `remaining_amount`, `status`
- `app/Models/CreditCard.php`, `app/Enums/CreditCardStatus.php` — `current_balance`, `status`

### Dashboard integration point
- `app/Http/Controllers/Api/V1/DashboardController.php` — existing dashboard API conventions
- `.planning/phases/24-cash-flow-forecast-safe-to-spend/24-CONTEXT.md` — sibling phase also adding dashboard aggregate figures; plan/execute with awareness of it to keep the dashboard additions stylistically consistent

No external specs — requirements fully captured in decisions above.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `SubscriptionService::calculateMonthlyCost()` / `getBillingAmount()` — subscription normalization, already built
- `ResolvesUserCurrency` trait — currency resolution for chatbot responses, already built
- `IntentRouter`'s allow-list design — adding an intent is additive (new class + one array entry), no router logic changes needed

### Established Patterns
- Every `ChatIntent` filters by `user_id` unless `hasRole('superadmin')`, then filters by an active/non-closed status enum, then maps to the `{label, value, currency, detail}` items shape with a `highlight` total

### Integration Points
- `IntentRouter::SUPPORTED_INTENTS` array (5 new entries)
- Service container binding for the new intent classes (wherever `AccountBalancesIntent` etc. are currently bound — likely a service provider)
- `DashboardController` for the SPA-facing figures

</code_context>

<specifics>
## Specific Ideas

The user's own framing ("perché non entrambe le opzioni?") set the pattern for this whole phase: prefer combining options (normalized total + raw detail; individual intents + one combined intent) over picking one when both are cheap to deliver together.

</specifics>

<deferred>
## Deferred Ideas

None raised beyond this phase's scope during this discussion.

### Reviewed Todos (not folded)
None — no pending todo backlog was cross-referenced for this ad-hoc phase.

</deferred>

---

*Phase: 26-debt-and-subscription-totals-chatbot-and-dashboard*
*Context gathered: 2026-09-17*
