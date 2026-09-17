# Phase 24: Cash-flow forecast ("safe to spend") - Context

**Gathered:** 2026-09-17
**Status:** Ready for planning

<domain>
## Phase Boundary

Add a "safe to spend over the next 30 days" figure to the existing SPA Dashboard: current available balance minus every already-tracked upcoming commitment (loan installments, subscription renewals, credit card cycle payments) due within that window. **Major reuse finding from scouting:** `app/Services/UpcomingPaymentsService::forUser(User $user, int $days = 3)` (built in Phase 17 for the chatbot, already powering `DashboardController::upcomingPayments()`) already returns exactly this set — loan/credit-card/subscription items with `amount` and `due_date` — for an arbitrary `$days` window. This phase is primarily: (1) an "available balance" query that excludes accounts tied to an active `SavingGoal`, (2) summing `UpcomingPaymentsService::forUser($user, 30)` amounts, (3) exposing the delta via the dashboard API and SPA, not a new aggregation engine.

</domain>

<decisions>
## Implementation Decisions

### Horizon
- **D-01:** Fixed 30-day window, not user-configurable. Reuses `UpcomingPaymentsService::forUser($user, 30)` — no new parameter surface needed beyond passing `30` instead of the chatbot's default `3`.

### What counts as an upcoming commitment
- **D-02:** Loan installments due (`LoanPayment`), subscription renewals due (`Subscription`), and the current credit-card cycle's payment (`CreditCardPayment`) — all three already returned by `UpcomingPaymentsService::forUser()`. No new commitment types in this phase.

### Available balance baseline
- **D-03:** Sum `Account.balance` across the user's active accounts, **excluding any account linked to an active `SavingGoal`** (`SavingGoal.account_id`, `status = active`) — money earmarked for a savings goal is not "safe to spend." If an account has both goal-linked and non-goal-linked purposes, the whole account is excluded (no partial/sub-account split exists in the data model — this is a known simplification, not a bug, and should be documented as such).

### Surface
- **D-04:** New data point on the existing SPA Dashboard (via `DashboardController`), not a new Artisan command. Consistent with how the rest of the dashboard already surfaces aggregate finance data.

### The agent's Discretion
- Exact new DashboardController method/response shape (e.g., extend the existing `charts()` payload vs. a new endpoint) — follow whatever is more consistent with the existing dashboard API shape
- Whether to also show the 30-day commitment list (not just the net number) — the decisions above only require the net "safe to spend" figure; showing the breakdown too is a reasonable enhancement within scope if cheap, since `UpcomingPaymentsService` already returns per-item data
- SPA component/placement details

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Primary reusable asset (read first — changes this phase's scope)
- `app/Services/UpcomingPaymentsService.php` — `forUser(User $user, int $days = 3)` already aggregates loan/credit-card/subscription upcoming amounts with due dates; call with `$days = 30` instead of building new aggregation logic
- `app/Http/Controllers/Api/V1/DashboardController.php` — `charts()` and `upcomingPayments()` show the existing dashboard API conventions and already inject `UpcomingPaymentsService`

### Balance baseline
- `app/Models/Account.php` — `balance` field (float cast), `is_active`
- `app/Models/SavingGoal.php` — `account_id`, `status` (enum `SavingGoalStatus`) — used to determine which accounts to exclude

No external specs — requirements fully captured in decisions above.

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `UpcomingPaymentsService::forUser()` — the entire "what's coming due" half of this phase, already built and already user-scoped (respects superadmin vs owner filtering)
- `DashboardController` — existing DI pattern (`UpcomingPaymentsService` already injected via constructor) to extend

### Established Patterns
- Dashboard API returns JSON via `JsonResponse` from controller methods scoped to `$request->user()`

### Integration Points
- Likely a new controller method or an addition to the existing `charts()` payload in `DashboardController`
- SPA: wherever the dashboard already renders `upcomingPayments`/`charts` data is the natural place to add this

</code_context>

<specifics>
## Specific Ideas

No specific UI mockup given — the user deferred exact presentation (number-only vs number + breakdown) to implementation discretion.

</specifics>

<deferred>
## Deferred Ideas

- Configurable horizon (7/14/60 days) — user chose fixed 30 for now
- Handling partial/sub-account savings allocations (an account that's *partly* earmarked for a goal and partly free) — not supported by the current data model, out of scope
- A dedicated Artisan command for a detailed forecast breakdown — user chose dashboard-only for this phase

### Reviewed Todos (not folded)
None — no pending todo backlog was cross-referenced for this ad-hoc phase.

</deferred>

---

*Phase: 24-cash-flow-forecast-safe-to-spend*
*Context gathered: 2026-09-17*
