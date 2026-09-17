# Phase 21: Credit card balance recompute correctness - Context

**Gathered:** 2026-09-17
**Status:** Ready for planning

<domain>
## Phase Boundary

`CreditCardCycleService::syncCardBalance()` recomputes a card's `current_balance` purely as `sum(expenses.amount) - sum(paid principal_amount)`, with no opening-balance term. It runs on every `CreditCardPayment` create/update via `CreditCardPaymentObserver`, silently dropping any balance not backed by `CreditCardExpense` rows — including the directly-editable `current_balance` value set on card creation (a shipped, user-facing field in both the Filament form and the REST API). This phase fixes the formula and its data-integrity fallout without reintroducing the non-idempotent race condition Phase 18 was fixing when it introduced this recompute. It is a correctness-fix phase, not a feature-enhancement phase.

</domain>

<decisions>
## Implementation Decisions

### Balance model
- **D-01:** Add an `opening_balance` column to `CreditCard` (mirrors `Account.opening_balance`). Fix `syncCardBalance()` to compute `opening_balance + expenses - paid_principal` instead of `expenses - paid_principal`. This preserves the idempotent recompute-from-source approach Phase 18 introduced (avoids reintroducing the double-delta race) while correcting the formula itself.
- **D-02:** `opening_balance` (or whatever field replaces the current `current_balance` semantics) must stay freely editable after card creation, exactly like `current_balance` is editable today in the Filament form and via `sometimes|nullable` on `UpdateCreditCardRequest`. Do not lock it after first save.
- **D-03:** No additional locking mechanism (e.g. `lockForUpdate()`) is needed beyond the idempotent recompute itself. Two concurrent payment-status updates both triggering `syncCardBalance()` will independently compute the same correct result once the formula includes `opening_balance` — this is the same idempotency property Phase 18 already relied on, just with a corrected formula.

### handleDeletedPayment() alignment
- **D-04:** Align `CreditCardCycleService::handleDeletedPayment()` to the same model. It currently still uses the older delta-based `CreditCardBalanceService::reversePrincipalPayment()` instead of the authoritative recompute — flagged as a lower-severity carry-forward in Phase 18's `deferred-items.md` and again in `.planning/codebase/CONCERNS.md`. Fix it in this phase rather than deferring again.
- **D-05:** Route payment deletion through the same `syncCardBalance()` call used for create/update, rather than keeping a separate `reversePrincipalPayment()` code path. One authoritative recompute function for all three payment lifecycle events (create, update, delete).

### Test coverage
- **D-06:** The two existing Phase 19 regression tests (`CreditCardCreditLineSyncTest::payments_reintegrate_only_principal_on_status_changes`, `CreditCardKpiServiceTest::it_returns_expected_credit_card_kpis_for_user`) must pass unmodified — they already assert the correct expected values, only the implementation was wrong.
- **D-07:** Re-verify `tests/Unit/CreditCardCycleServiceTest.php::duplicate_payment_unmark_sync_does_not_double_restore_balance` (the Phase 18 race-condition regression test) still passes with the corrected formula — confirms the fix doesn't reintroduce the race Phase 18 closed. Extend it if the `opening_balance` term changes its assertions.
- **D-08:** Add a migration/backfill test that verifies existing cards retain a stable, non-drifting balance across the migration (see D-09/D-10 below) — this touches real financial data, so the migration itself needs a correctness proof, not just manual review.

### Data backfill for existing cards
- **D-09:** Do NOT attempt to auto-reconstruct historical `opening_balance` values for existing cards — for any card with a `CreditCardPayment` created/updated since the bug's introduction (Phase 18, commit range around 2026-08-06), the true original balance is no longer derivable from current data (the bug already overwrote it). Backfill `opening_balance = 0` for all existing cards as a neutral migration (no immediate behavior change for cards untouched by the bug).
- **D-10:** Before the neutral migration ships, produce a report (Artisan command or equivalent) listing which existing cards are "at risk" — i.e. have at least one `CreditCardPayment` with `created_at` or `updated_at` after the bug's introduction date. This lets the user manually cross-check those specific cards against real statements and correct `opening_balance`/`current_balance` via Filament after deploy, rather than reviewing every card blind.

</decisions>

<canonical_refs>
## Canonical References

**Downstream agents MUST read these before planning or implementing.**

### Origin and prior investigation of this bug
- `.planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/18-CONTEXT.md` — D-02/D-03 established the "recompute from source instead of delta" fix that introduced this regression; explains WHY the authoritative recompute exists (non-idempotent double-delta race) so the Phase 21 fix must preserve that property
- `.planning/phases/19-revolving-credit-card-interest-engine-correctness-align-cycl/deferred-items.md` — confirms both failing tests reproduce identically on the unmodified `main` baseline (commit `f627aaf`), i.e. this is a real pre-existing bug, not a stale/wrong test fixture
- `.planning/codebase/CONCERNS.md` (Known Bugs section, ~line 37; Balance fragility note, ~lines 139-140) — documents `handleDeletedPayment()`'s still-unaligned delta pattern and the general balance-fragility risk across multiple services touching the same data

### Code the fix must touch
- `app/Services/CreditCardCycleService.php` — `syncCardBalance()` (~line 420-430, the buggy formula), `syncCycleAndCardFromPayment()` (~line 287-337, calls it on create/update), `handleDeletedPayment()` (~line 339-384, still delta-based)
- `app/Observers/CreditCardPaymentObserver.php` — fires `syncCycleAndCardFromPayment()`/`handleDeletedPayment()` on every payment create/update/delete
- `app/Services/CreditCardBalanceService.php` — the delta-based pattern (`addExpense`, `removeExpense`, `applyPrincipalPayment`, `reversePrincipalPayment`) that expenses still correctly use and that `handleDeletedPayment()` currently (incorrectly) still calls for payments
- `app/Models/CreditCard.php` / `app/Filament/Resources/CreditCards/Schemas/CreditCardForm.php` (~line 119) / `app/Http/Requests/Api/StoreCreditCardRequest.php` / `app/Http/Requests/Api/UpdateCreditCardRequest.php` — where `current_balance` is directly editable today; `opening_balance` needs the same editability (D-02)
- `app/Models/Account.php` (`opening_balance` cast/fillable) and its migrations (`database/migrations/2026_03_15_183138_add_opening_balance_to_accounts_table.php`, `..._fix_opening_balance_signed_in_accounts_table.php`) — the existing pattern to mirror for the new `CreditCard.opening_balance` column

### Tests to reuse/extend
- `tests/Unit/CreditCardCreditLineSyncTest.php:93` and `tests/Unit/CreditCardKpiServiceTest.php:124` — must pass unmodified (D-06)
- `tests/Unit/CreditCardCycleServiceTest.php::duplicate_payment_unmark_sync_does_not_double_restore_balance` — race-condition regression to re-verify (D-07)
- `tests/Feature/CreditCardLifecycleIntegrationTest.php::repeated_mark_paid_requests_do_not_drift_card_balance` — Phase 18's integration-level race coverage, also re-verify

</canonical_refs>

<code_context>
## Existing Code Insights

### Reusable Assets
- `Account.opening_balance` — existing, shipped pattern for exactly this concept (signed decimal, editable via Filament/API); `CreditCard.opening_balance` should follow the same conventions (column type, cast, form field, request validation)

### Established Patterns
- Expenses use delta-based balance mutation (`CreditCardBalanceService::addExpense/removeExpense`) and are NOT affected by this bug — do not touch that path
- Payments (create/update) use authoritative recompute (`syncCardBalance()`) since Phase 18 — this phase corrects its formula, does not replace the pattern
- Payments (delete) still use the old delta path (`reversePrincipalPayment()`) — this phase migrates it to the same authoritative recompute (D-04/D-05)

### Integration Points
- `current_balance` is directly settable via Filament (`CreditCardForm.php:119`, required field, default 0) and via REST (`StoreCreditCardRequest`, `UpdateCreditCardRequest`) — any new `opening_balance` field needs equivalent exposure per D-02
- All three `CreditCardPaymentObserver` lifecycle hooks (`created`, `updated`, `deleted`) need to converge on the same `syncCardBalance()` call after this phase (D-05)

</code_context>

<specifics>
## Specific Ideas

- This is the user's real personal-finance data (Fluxa is used in production/uat with real credit card statements — see prior interest-engine validation work against real Amex statements in Phase 19). The backfill approach (D-09/D-10) was chosen specifically to avoid silently fabricating or guessing real financial figures — the user will manually verify at-risk cards against real statements after deploy.
- Bug window for the at-risk-card report (D-10): any `CreditCardPayment` created or updated on or after the Phase 18 hardening work (~2026-08-06, commit range around `18-04`/`18-05`/`18-06`). The exact commit boundary should be re-verified during planning/research against `git log` rather than assumed from this date alone.

</specifics>

<deferred>
## Deferred Ideas

None — discussion stayed within phase scope.

### Reviewed Todos (not folded)
None — `todo match-phase 21` returned zero matches.

</deferred>

---

*Phase: 21-credit-card-balance-recompute-correctness-fix-synccardbalance-dropping-opening-balance-on-payment-create-update*
*Context gathered: 2026-09-17*
