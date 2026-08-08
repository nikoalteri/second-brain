---
phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl
plan: 03
subsystem: billing
tags: [laravel, credit-cards, interest-calculation, phpunit, tdd]

# Dependency graph
requires: [19-01]
provides:
  - "calculateDailyBalances() applies PAID payments' principal_amount on their actual_date ?? due_date, scoped to the cycle and window"
  - "calculatePaymentBreakdown() branches on fixed_payment_includes_stamp_duty to select the inclusive vs exclusive principal/total_due formula"
affects: [19-04, 19-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Daily-balance walk reconstructs the cycle's opening balance by undoing its own mutations (subtract this cycle's expenses, add back this cycle's paid principal) before replaying both day by day, so the final day reconciles to current_balance without double-subtracting"
    - "Card-level boolean configuration flags read once near the top of calculatePaymentBreakdown() and used to select between two mutually exclusive formula branches, both sharing the same max(0.0, ...) clamp"

key-files:
  created: []
  modified:
    - app/Services/RevolvingCreditCalculator.php
    - tests/Unit/CreditCardDailyBalanceTest.php

key-decisions:
  - "Payments are scoped via $cycle->payments() (FK-scoped) and additionally filtered to [period_start_date, statement_date] rather than a card-wide date-range query, to prevent an adjacent cycle's payment from leaking into this cycle's walk (T-19-09)"
  - "calculateDailyBalances() stays pure/read-only — no update()/save() calls added — so Phase 18's syncCardBalance() DB::transaction() remains the single balance-mutation path (T-19-08)"
  - "Test fixtures for the stamp-duty-inclusion tests create the prior cycle as OPEN first (so the anchoring expense attaches to it directly instead of triggering an unrelated auto-created 'current month' cycle from CreditCardExpenseService::resolveCycle), then flip it to PAID afterward once mutable"
  - "Test fixtures always call $card->refresh() immediately before any $card->update(['current_balance' => ...]) override — without the refresh, Eloquent's dirty-check can compare the new value against a stale in-memory attribute equal to it and silently skip the actual UPDATE query (see Deviations)"

patterns-established:
  - "When a test fixture needs to force current_balance to a specific final value after side-effect-driven mutations (expense/payment observers), refresh the model from the DB immediately before the override update() call, not just after it"

requirements-completed: [D-01, D-03]

# Metrics
duration: ~65min (including two connection-interruption resumes)
completed: 2026-08-08
---

# Phase 19 Plan 03: Payment-Aware Daily Balance Walk & Stamp-Duty Payment Breakdown Summary

**Fixed `RevolvingCreditCalculator::calculateDailyBalances()` to subtract PAID payments' principal from the running daily balance on their effective date (previously only expenses were applied, inflating interest for any cycle containing a payment), and branched `calculatePaymentBreakdown()` on the `fixed_payment_includes_stamp_duty` flag so inclusive-mode cards correctly absorb the stamp duty into the fixed payment instead of billing it twice.**

## Performance

- **Duration:** ~65 min (execution was interrupted twice by upstream connection errors mid-Task 2; each resume picked up from the last commit with no rework of already-committed Task 1)
- **Completed:** 2026-08-08
- **Tasks:** 2 completed
- **Files modified:** 2

## Accomplishments

- `calculateDailyBalances()` now groups PAID payments by `actual_date ?? due_date` (mirroring `CreditCardPaymentPostingService`), scopes them to `$cycle->payments()`, filters out any payment whose effective date falls outside the cycle's `[period_start_date, statement_date]` window, and subtracts only the `principal_amount` (never interest or stamp duty) from the running balance on that date.
- The walk's opening balance is reconstructed by undoing the cycle's own mutations — `current_balance - cycleSpent + cyclePaidPrincipal` — so replaying expenses and payments day-by-day lands exactly on `current_balance` on the final day, with no double-subtraction.
- PENDING payments are ignored entirely by the walk (they have not moved money yet), matching the authoritative `syncCardBalance()` formula which only sums `principal_amount` over PAID payments.
- `calculatePaymentBreakdown()` reads `fixed_payment_includes_stamp_duty` once and branches: the inclusive-mode formula computes `principal = installment - interest - stamp_duty` and `total_due = fixed_payment`; the exclusive-mode (default) formula is byte-identical to the pre-existing behavior (`principal = installment - interest`, `total_due = installment + stamp_duty`). Both branches retain the `max(0.0, ...)` clamp on `principal_amount`.
- Six new `#[Test]` methods added to `tests/Unit/CreditCardDailyBalanceTest.php` (two for the payment-aware walk, two for the stamp-duty branch, plus the pre-existing missing-import fix from Task 1 applies to the whole file). All eight tests in the file — the four pre-existing plus the six new ones — pass with 0 failures.

## Task Commits

Each task followed the RED → GREEN TDD cycle and was committed atomically:

1. **Task 1: Apply PAID payments inside the daily-balance day loop**
   - RED `9b3a4ea` (test): added `mid_cycle_paid_payment_reduces_daily_balance_from_its_actual_date` and `pending_payment_does_not_reduce_daily_balance`, plus the missing `use PHPUnit\Framework\Attributes\Test;` import that was silently causing zero test discovery in this file before this plan
   - GREEN `511f7b9` (feat): payment-aware day loop implemented in `RevolvingCreditCalculator::calculateDailyBalances()`

2. **Task 2: Branch calculatePaymentBreakdown on fixed_payment_includes_stamp_duty**
   - RED `ca63a0e` (test): added `payment_breakdown_charges_stamp_duty_on_top_when_flag_is_off` (already passing — proves the exclusive default is unchanged) and `payment_breakdown_absorbs_stamp_duty_into_the_installment_when_flag_is_on` (failing — inclusive branch not yet implemented)
   - GREEN `688b608` (feat): two-branch formula implemented in `RevolvingCreditCalculator::calculatePaymentBreakdown()`

**Plan metadata:** committed separately by the orchestrator after wave completion (worktree mode — STATE.md/ROADMAP.md are not touched by this agent)

## Files Created/Modified

- `app/Services/RevolvingCreditCalculator.php` — `calculateDailyBalances()`: loads `payments` relation, groups PAID payments by effective date scoped to the cycle window, subtracts principal in the day loop, reconstructs the opening balance to account for the cycle's own paid principal. `calculatePaymentBreakdown()`: reads `fixed_payment_includes_stamp_duty`, branches the installment/principal/total_due computation into inclusive vs exclusive formulas, both clamped at 0.
- `tests/Unit/CreditCardDailyBalanceTest.php` — Added `use PHPUnit\Framework\Attributes\Test;`, `use App\Enums\CreditCardPaymentStatus;`, `use App\Models\CreditCardPayment;` imports; added 4 new `#[Test]` methods covering mid-cycle PAID payments, PENDING payments being ignored, and both stamp-duty-inclusion modes. All fixtures use only synthetic 2027 dates and amounts per D-04 (no real statement figures or provenance comments).

## Decisions Made

- Followed the plan's exact interface guidance for the day-loop rewrite and the payment-breakdown branch — no deviation from the specified formulas or field names.
- For the stamp-duty-branch test fixtures, deliberately created the "prior" cycle as OPEN first and flipped it to PAID after the anchoring expense was attached, to avoid `CreditCardExpenseService::resolveCycle()` auto-creating an unrelated third "current month" cycle when no matching OPEN cycle existed yet for the expense's date. This is a test-fixture-only decision; no application code was changed for it.
- Added an explicit `$card->refresh()` immediately before every `$card->update(['current_balance' => ...])` override in the new stamp-duty-branch tests (see Deviations below for why this was necessary).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Test fixture silently failed to override current_balance due to Eloquent dirty-check against a stale in-memory attribute**
- **Found during:** Task 2, while debugging why the stamp-duty-branch tests computed interest on double the intended balance (2000 instead of 1000)
- **Issue:** The plan's fixture pattern is `$card->update(['current_balance' => X]); $card->refresh();` — set-then-refresh. When the target override value `X` happens to equal the value the in-memory `$card` object was created with (before any expense/payment side effects mutated the DB row directly via `CreditCardBalanceService`), Eloquent's `update()` compares the new value against its own stale in-memory attribute, finds no difference, and skips issuing the actual `UPDATE` query — leaving the DB row at whatever the side-effect services had set it to (e.g., after an expense observer bumped it).
- **Fix:** Reordered to `$card->refresh(); $card->update([...]); $card->refresh();` in both new stamp-duty-branch tests, so the dirty-check compares against the true current DB value before applying the override. Also switched the fixtures' initial `current_balance` to `0` (rather than the plan's suggested `1000`, which coincided with the target override value and was the actual trigger for the stale-dirty-check bug) to make the override unconditionally register regardless of dirty-check timing.
- **Files modified:** `tests/Unit/CreditCardDailyBalanceTest.php` (test fixtures only; no application code)
- **Verification:** Both stamp-duty-branch tests now correctly observe `current_balance = 1000.00` at breakdown time; `interest_amount` matches the expected `11.89` (constant 1000 balance × 31 days), not the previously-observed `23.78` (constant 2000 balance).
- **Committed in:** `ca63a0e` (RED, fixture already correct) and confirmed passing in `688b608` (GREEN)

**2. [Rule 3 - Blocking] `CreditCardExpenseService::resolveCycle()` rejects expenses dated within an already-PAID cycle's window**
- **Found during:** Task 2, first fixture attempt (creating the "prior" cycle as `PAID` before attaching the anchoring expense)
- **Issue:** `ensureCycleIsMutable()` throws a `ValidationException` ("This billing cycle has already been issued...") when an expense's `spent_at` falls inside a non-OPEN cycle's date range, since `CreditCardExpenseObserver::creating()` calls `validateExpenseChange()` before every expense create.
- **Fix:** Reordered the fixture to create the prior cycle as `OPEN`, create the anchoring expense (which attaches to it while it's still mutable), then update the prior cycle's status to `PAID` afterward. Confirmed this update does not spuriously create an unwanted `CreditCardPayment` via `CreditCardCycleObserver::updated()`, because the factory-default `total_due`/`paid_amount` are both `0.00`, making `$unpaidAmount <= 0` true and short-circuiting payment creation.
- **Files modified:** `tests/Unit/CreditCardDailyBalanceTest.php` (test fixtures only)
- **Verification:** Both stamp-duty-branch tests create exactly the two cycles the plan's `<behavior>` describes (a prior paid cycle plus the target open cycle), with no unrelated third auto-created cycle.
- **Committed in:** `ca63a0e`

---

**Total deviations:** 2 auto-fixed (1 bug, 1 blocking), both confined to test-fixture construction in `tests/Unit/CreditCardDailyBalanceTest.php`. No deviation from the plan's specified `RevolvingCreditCalculator.php` formulas, field names, or branch logic — both application-code edits match the plan's `<action>` blocks exactly.

**Impact on plan:** None on scope or the shipped calculator logic. Both deviations were pre-existing test-infrastructure interaction gaps (Eloquent dirty-checking semantics; the expense-service's cycle-mutability guard) surfaced by the plan's own fixture design, not consequences of any calculator code written by this plan.

## Issues Encountered

- Two upstream connection interruptions occurred mid-Task 2 (documented in the coordinator's resume messages). Both resumes correctly found Task 1's commits intact (`9b3a4ea`, `511f7b9`) and picked up from the last committed state without any rework or duplicate commits.
- `php artisan test --filter=RevolvingCreditCalculatorTest` shows no new failures — all 11 tests pass, including `it_validates_user_bank_statement_14_percent`, confirming this plan's daily-balance-walk change does not alter behavior for cycles with no payments.
- `php artisan test --testsuite=Unit` (103 tests total): 101 passed, 2 failed. Both failures are pre-existing and unrelated to this plan's files, already logged in `.planning/phases/19-revolving-credit-card-interest-engine-correctness-align-cycl/deferred-items.md` by plans 19-01 and 19-02 (verified identical failure messages: `CreditCardCreditLineSyncTest::payments_reintegrate_only_principal_on_status_changes` — "Failed asserting that 0.0 is identical to 500.0" at line 93; `CreditCardKpiServiceTest::it_returns_expected_credit_card_kpis_for_user` — "Failed asserting that 120.0 is identical to 980.0" at line 124). Neither test touches `RevolvingCreditCalculator.php` or `CreditCardDailyBalanceTest.php`. Not investigated further per the scope-boundary rule; no new entry added to `deferred-items.md` since these are the exact same pre-existing failures already documented there.
- The worktree had no `vendor/` directory at all (not even a stale symlink, unlike prior waves). Ran a full local `composer install` at the start of execution, which also republished Filament's front-end assets. `vendor/` and `.env` remain gitignored and were not committed. The worktree also had no `.env`; copied from `.env.example` and ran `php artisan key:generate --force` — this is a local dev-environment file, gitignored, not committed.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- Both of the two D-01 causal-chain defects owned by this plan (payment-unaware daily-balance walk; stamp-duty-ignoring payment breakdown) are fixed and covered by regression tests.
- `calculateInterestDirectMonthly()`'s ~12x-too-high-rate defect (the fourth of the four original D-01 discrepancies) is explicitly out of scope for this plan and remains for a downstream plan (19-04 per the phase's wave breakdown) to address — `daily_balance` stays the default calculation method so this landmine is not triggered by any card unless `interest_calculation_method` is explicitly switched.
- The two pre-existing, unrelated `CreditCardCreditLineSyncTest`/`CreditCardKpiServiceTest` failures remain open in `deferred-items.md` for a future dedicated investigation, as flagged independently by 19-01 and 19-02.
- Sibling plans 19-04 and 19-05 were not inspected for interaction; this plan touched only `RevolvingCreditCalculator.php` and its test file, matching the plan's declared `files_modified`.

---
*Phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl*
*Completed: 2026-08-08*
