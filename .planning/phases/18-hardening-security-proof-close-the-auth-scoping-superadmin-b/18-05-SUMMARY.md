---
phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
plan: 05
subsystem: credit-cards
tags: [validation, data-integrity, observers, testing, credit-card]
requires:
  - phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
    plan: 04
    provides: "Idempotent CreditCardCycleService::syncCycleAndCardFromPayment balance recompute"
provides:
  - "Fail-closed validation for missing credit_card_id in expense create/update"
  - "Automated residual-state and reentrancy proof for both credit-card observers"
affects:
  - app/Services/CreditCardExpenseService.php
  - app/Observers/CreditCardExpenseObserver.php
  - app/Observers/CreditCardPaymentObserver.php
tech-stack:
  added: []
  patterns:
    - "ReflectionProperty-based static-state assertion for private static observer arrays"
key-files:
  created:
    - tests/Unit/Observers/ObserverStaticStateTest.php
  modified:
    - app/Services/CreditCardExpenseService.php
    - app/Observers/CreditCardExpenseObserver.php
    - app/Observers/CreditCardPaymentObserver.php
    - tests/Feature/CreditCardExpenseIntegrationTest.php
key-decisions:
  - "Replaced the fail-open `if (! $currentCard) { return; }` in CreditCardExpenseService::validateExpenseChange() with ValidationException::withMessages(['credit_card_id' => ...]), matching the existing ensureCycleIsMutable() throw convention"
  - "Task 3 took Branch B (document, do not fix): Task 2's reproduction showed a failed mid-update write can leave residue in CreditCardPaymentObserver::$previousStatuses, but the subsequent legitimate update still produced the correct cycle status and card balance because syncCycleAndCardFromPayment() recomputes the balance authoritatively (post-18-04) rather than applying the stale previousStatus as a delta"
patterns-established:
  - "Private static observer bookkeeping arrays get docblock-documented D-02 dispositions with a pointer to their proof test, instead of silent unexamined risk"
requirements-completed: [D-01, D-02, D-03]
duration: 15min
completed: 2026-08-06
---

# Phase 18 Plan 05: Close credit-card expense validation and observer static-state findings Summary

Fail-closed `ValidationException` now rejects moving or creating a `CreditCardExpense` against a non-existent `credit_card_id` (previously silently accepted), and both credit-card observers' static bookkeeping arrays now have automated residual-state proof with an evidence-backed D-02 severity disposition recorded in-code.

## Performance

- **Duration:** ~15 min
- **Tasks:** 3
- **Files modified:** 5 (1 created, 4 modified)

## Accomplishments

- `CreditCardExpenseService::validateExpenseChange()` throws `ValidationException` instead of silently returning when the target `credit_card_id` does not exist, on both create and update paths
- Added `tests/Unit/Observers/ObserverStaticStateTest.php` proving both `CreditCardExpenseObserver::$originalPointers` and `CreditCardPaymentObserver::$previousStatuses` are cleared after successful updates, do not cross-contaminate across sequential updates on distinct records, and — when a failed mid-update write does leave residue — that the residue does not corrupt the subsequent legitimate update's output
- Applied D-02's severity policy to the measured residue (Branch B: document, do not fix) with docblocks on both static properties pointing at the proof test

## Task Commits

Each task was committed atomically:

1. **Task 1: Make expense validation fail closed on a missing target credit card** - `f66e422` (feat)
2. **Task 2: Prove observer static state leaves no residue and attempt an intra-request contamination reproduction** - `253421e` (test)
3. **Task 3: Apply the D-02 severity decision to the observer static-state finding** - `d6a7ef1` (docs)

_Note: Task 1 and Task 2 both had TDD-style RED reproduction runs before their commits (see Verification below), but each landed as a single commit per plan file scope._

## Files Created/Modified

- `app/Services/CreditCardExpenseService.php` - `validateExpenseChange()` now throws `ValidationException::withMessages(['credit_card_id' => 'The selected credit card does not exist.'])` instead of returning silently when the target card is missing
- `tests/Feature/CreditCardExpenseIntegrationTest.php` - Added `moving_expense_to_nonexistent_card_is_rejected`, `creating_expense_on_nonexistent_card_is_rejected`, `moving_expense_between_two_real_cards_still_succeeds`
- `tests/Unit/Observers/ObserverStaticStateTest.php` (new) - Four tests proving observer static-state clearance, cross-contamination safety, and the measured effect of a failed-write residue
- `app/Observers/CreditCardExpenseObserver.php` - Docblock added above `$originalPointers` recording the D-02 proof reference (no logic change)
- `app/Observers/CreditCardPaymentObserver.php` - Docblock added above `$previousStatuses` recording the D-02 proof reference and the measured residue evidence (no logic change)

## Decisions Made

- **Task 1 fail-closed change had no fallout on the existing suite.** No existing test relied on the silent-return behavior; the full suite (188 tests, 812 assertions) stayed green except the pre-existing unrelated `FinanceReportPageTest` failure, so no fixture rework or explicit null-guard was needed beyond the plan's prescribed fix.
- **Task 3 Branch B selected over Branch A.** Task 2's reproduction (`test_failed_write_does_not_leave_contaminating_static_state`) forced a FK violation on `$payment->update(['credit_card_id' => 999999])` after `updating()` had already populated `CreditCardPaymentObserver::$previousStatuses`. The residue was **not empty** (`{"1":"pending"}` — verbatim, captured via a temporary `fwrite(STDERR, ...)` debug probe during investigation, removed before commit). The test then performed a legitimate `mark-paid` update on the same payment id and asserted the resulting `cycle->status === PAID` and `card->current_balance === 0.0` — both correct. Because `CreditCardCycleService::syncCycleAndCardFromPayment()` was changed in plan 18-04 to recompute the card balance authoritatively (`syncCardBalance()`) rather than applying the caller-supplied `$previousStatus` as a delta, the stale residue has no way to corrupt the balance; it can only affect the cycle's `OVERDUE` branch decision, which was not exercised as incorrect in this fixture. Per D-02, this is a narrow edge case with no observed data-integrity or disclosure impact — documented in-code, not fixed, and carried forward for `deferred-items.md` in plan 18-06 (per the plan's Branch B instructions).

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Missing `vendor/` directory in this worktree**
- **Found during:** Task 1, first `php artisan test` run (`Failed to open stream: vendor/autoload.php`)
- **Issue:** This worktree had no `vendor/` at all (not even a stale symlink)
- **Fix:** Created `vendor -> /Users/nikoalteri/Documents/Dev/second-brain/vendor` symlink, matching the shared-vendor pattern documented in plan 18-04's SUMMARY for parallel worktree agents
- **Files modified:** none (symlink only, not a tracked file)
- **Verification:** `php artisan test` ran successfully afterward

**2. [Rule 3 - Blocking] Stale shared autoloader after the fix landed but before `composer dump-autoload`**
- **Found during:** Task 1, second test run — the `ValidationException` fix appeared to not take effect (still saw `QueryException`)
- **Issue:** Same shared-`vendor/` autoloader staleness pattern documented in plan 18-04's SUMMARY (another parallel worktree agent's `composer dump-autoload` last "won" the shared classmap)
- **Fix:** Ran `composer dump-autoload` from this worktree before re-running tests
- **Files modified:** none (autoloader regeneration only)
- **Verification:** Fix took effect; all 7 `CreditCardExpenseIntegrationTest` tests passed afterward

---

**Total deviations:** 2 auto-fixed (both Rule 3 - Blocking, both environment-only, no code changes)
**Impact on plan:** No scope creep. Both deviations were required to run the test suite at all in this worktree and left no trace in tracked files.

## Issues Encountered

None beyond the environment blockers documented above.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- Both confirmed findings from the plan's objective are closed: the fail-open expense validation is now fail-closed with regression coverage, and the observer static-state risk has automated proof plus an evidence-backed D-02 disposition.
- T-18-05-02's Branch B disposition (documented, not fixed) should be added to `deferred-items.md` by plan 18-06, per this plan's own instructions — not done here as it is out of this plan's file scope.
- Full suite: 187 passed, 1 pre-existing unrelated failure (`FinanceReportPageTest`, confirmed pre-existing on the base commit by plan 18-04).

## Threat Flags

None — this plan's threat model (T-18-05-01 through T-18-05-04) already anticipated all surfaces touched; no new surface introduced.

## Known Stubs

None.

---
*Phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b*
*Completed: 2026-08-06*

## Self-Check: PASSED

- `app/Services/CreditCardExpenseService.php` — FOUND
- `tests/Unit/Observers/ObserverStaticStateTest.php` — FOUND
- `app/Observers/CreditCardExpenseObserver.php` — FOUND
- `app/Observers/CreditCardPaymentObserver.php` — FOUND
- `.planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/18-05-SUMMARY.md` — FOUND
- Commit `f66e422` — FOUND
- Commit `253421e` — FOUND
- Commit `d6a7ef1` — FOUND
- Commit `841bdf5` — FOUND
