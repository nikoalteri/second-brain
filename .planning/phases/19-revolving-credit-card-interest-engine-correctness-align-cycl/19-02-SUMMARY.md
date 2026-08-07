---
phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl
plan: 02
subsystem: payments
tags: [laravel, credit-cards, billing-cycles, phpunit]

# Dependency graph
requires: []
provides:
  - "ensureCurrentMonthCycle() anchors period_start_date to the previous cycle's stored statement_date + 1 day"
  - "calculateRevolvingPaymentBreakdown() applies annual/12 as the monthly rate instead of the full annual rate"
affects: [19-01, 19-03, 19-04, 19-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Cycle period derivation reads the previous cycle's persisted statement_date instead of recomputing it, avoiding re-derivation drift"
    - "firstOrCreate match keys narrowed to a strict subset of the DB unique index to stay idempotent across derivation changes"

key-files:
  created: []
  modified:
    - app/Services/CreditCardCycleService.php
    - tests/Unit/CreditCardCycleServiceTest.php

key-decisions:
  - "period_start_date is now anchored to previous cycle's statement_date + 1 day; first cycle (no predecessor) falls back to the statement month's calendar start, which cannot affect computed interest because isFirstCycle() forces interest to 0.0"
  - "firstOrCreate now matches on (credit_card_id, statement_date) only, moving period_month and period_start_date into the create-values array, since matching on the derived period_start_date would risk duplicate rows whenever the derivation changes"
  - "Legacy calculateRevolvingPaymentBreakdown's monthly rate corrected from rate/100 to rate/100/12, mirroring RevolvingCreditCalculator::calculateInterestDirectMonthly()"
  - "period_month now reflects the month the cycle CLOSES in, not the month period_start_date falls in (e.g. a Jun 7 -> Jul 6 cycle has period_month = '2027-07'); CyclesRelationManager derives period_month differently for manually-created cycles, which is a display-semantics difference only, not a functional break"

patterns-established:
  - "When re-deriving a persisted value from a related row, read that row's stored value directly rather than recomputing it independently — recomputation is exactly the class of off-by-one bug this plan removed"

requirements-completed: [D-01, D-02]

# Metrics
duration: 35min
completed: 2026-08-07
---

# Phase 19 Plan 02: Cycle Period Anchoring & Legacy Rate Fix Summary

**Fixed `CreditCardCycleService::ensureCurrentMonthCycle()` to anchor `period_start_date` off the previous cycle's stored `statement_date` (generic for any `statement_day`, including 30/31 clamped into short months) instead of `startOfMonth()`, and corrected the duplicate annual-rate-as-monthly-rate bug in the legacy `calculateRevolvingPaymentBreakdown()`.**

## Performance

- **Duration:** 35 min
- **Started:** 2026-08-07T00:37:00Z (approx, see task commit timestamps)
- **Completed:** 2026-08-07
- **Tasks:** 2
- **Files modified:** 2

## Accomplishments
- `ensureCurrentMonthCycle()` now derives `period_start_date` from the previous cycle's persisted `statement_date` + 1 day, correctly generalizing across any `statement_day` (including clamped day 30/31 in short months) and remaining idempotent for repeated calls on the same `(card, statement_date)`.
- First-cycle case (no predecessor row) falls back to the statement month's calendar start, documented as safe because `isFirstCycle()` forces interest to `0.0` for that cycle regardless of period length.
- `calculateRevolvingPaymentBreakdown()`'s monthly rate corrected from the full annual rate to `annual / 12`, matching `RevolvingCreditCalculator::calculateInterestDirectMonthly()`, closing the second live instance of the ~12x-too-high interest bug.
- Five new period-derivation unit tests added; four legacy breakdown test assertions corrected to the new, accurate expected values; all real-statement figures (`542`, `75.88`) and provenance comments removed per D-04.

## Task Commits

Each task was committed atomically:

1. **Task 1: Derive period_start_date from the previous cycle's stored statement_date** - `fe229e7` (fix)
2. **Task 2: Fix the flat monthly rate in the legacy calculateRevolvingPaymentBreakdown and correct its stale assertions** - `2abd544` (fix)

**Plan metadata:** (to be committed after this SUMMARY)

## Files Created/Modified
- `app/Services/CreditCardCycleService.php` - `ensureCurrentMonthCycle()` now reads the previous cycle's `statement_date` (ordered desc, filtered by `credit_card_id`) instead of calling `startOfMonth()`; `firstOrCreate()` match keys narrowed to `(credit_card_id, statement_date)`; `calculateRevolvingPaymentBreakdown()` monthly rate changed to `rate / 100 / 12` with an updated `@deprecated` docblock
- `tests/Unit/CreditCardCycleServiceTest.php` - Added 5 `ensure_current_month_cycle_*` tests (anchoring, no-previous fallback, day-31 clamp, day-30 clamp, idempotency); corrected 4 `revolving_breakdown_*` assertions to the `annual/12` figures; renamed `revolving_breakdown_with_14_percent_rate_matches_bank_statement` to `revolving_breakdown_splits_interest_and_principal_at_14_percent` and replaced its real-statement balance (`542`) with a synthetic one (`600`)

## Decisions Made
- Kept the existing `min(statement_day, daysInMonth)` clamp for `$statementDate` completely unchanged — the plan's read-first review confirmed it already correctly handles day 30/31 in short months, so only the `period_start_date` derivation needed to change.
- Did not touch `RevolvingCreditCalculator::isFirstCycle()` — it orders by `statement_date` and is deliberately decoupled from `period_start_date`, per plan instructions.

## Deviations from Plan

None — plan executed exactly as written. One local environment issue was diagnosed and resolved during execution (see Issues Encountered) but did not require any deviation from the plan's code changes.

## Issues Encountered

**Worktree vendor/autoload path resolution bug (self-diagnosed, not a plan deviation):** This worktree had no `vendor/` directory. Initially I created a symlink to the main repo's `vendor/`, which caused `vendor/composer/autoload_classmap.php`'s baked-in absolute paths to resolve back to the main repo's `app/` directory rather than the worktree's — so `php artisan test` was silently running the *original, unedited* `CreditCardCycleService.php` from the main checkout while my edits sat unused in the worktree. This was diagnosed via `ReflectionClass::getFileName()` after a `dd()` inserted in the edited file never fired. Fixed by replacing the symlink with a real `cp -R` of `vendor/` into the worktree followed by `composer dump-autoload -o`, after which `ReflectionClass::getFileName()` correctly pointed at the worktree's own file and all subsequent test runs exercised the actual edited code. `vendor/` and `.env` are both gitignored and were not committed.

**Pre-existing unrelated test failures (logged, not fixed — scope boundary):** `CreditCardCreditLineSyncTest::payments_reintegrate_only_principal_on_status_changes` and `CreditCardKpiServiceTest::it_returns_expected_credit_card_kpis_for_user` both fail identically before and after this plan's changes (verified via `git stash`/`git stash pop` against a clean baseline of this worktree). Neither touches `ensureCurrentMonthCycle` or `calculateRevolvingPaymentBreakdown`, so per the scope-boundary rule they were logged to `.planning/phases/19-revolving-credit-card-interest-engine-correctness-align-cycl/deferred-items.md` rather than fixed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- D-02 (period derivation) and the legacy-breakdown half of D-01's causal chain are fixed and covered by tests; `CreditCardExpenseService`'s range-match consumer (`whereDate('period_start_date' ... `statement_date'`) was not touched and its behavior is unaffected by the derivation change.
- Two pre-existing, unrelated test failures remain open in `deferred-items.md` for a future phase/plan to investigate (`CreditCardCreditLineSyncTest`, `CreditCardKpiServiceTest`).
- Sibling wave-1 plans (19-01, 19-03, 19-04, 19-05) were not inspected for interaction; this plan touched only `CreditCardCycleService.php` and its test file, matching the plan's declared `files_modified`.

---
*Phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl*
*Completed: 2026-08-07*

## Self-Check: PASSED

- FOUND: app/Services/CreditCardCycleService.php
- FOUND: tests/Unit/CreditCardCycleServiceTest.php
- FOUND: .planning/phases/19-revolving-credit-card-interest-engine-correctness-align-cycl/19-02-SUMMARY.md
- FOUND commit: fe229e7 (Task 1)
- FOUND commit: 2abd544 (Task 2)
