---
phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl
plan: 04
subsystem: billing
tags: [laravel, credit-cards, interest-calculation, phpunit]

# Dependency graph
requires:
  - phase: 19-03
    provides: "Payment-aware daily-balance walk and stamp-duty-branch payment breakdown, whose corrected figures this plan's synthetic test fixtures build on"
provides:
  - "calculateInterestDirectMonthly() charges a flat twelfth of the annual rate per month (annualRatePercent / 100 / 12) instead of the full annual rate"
  - "InterestCalculationMethod::DIRECT_MONTHLY description text matches the corrected formula"
  - "Regression coverage pinning the corrected 7.0 figure with an exact assertSame, so a regression back to the ~12x-too-high formula fails the suite"
affects: [19-05]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Flat-rate simplification methods (direct_monthly) stay intentionally simpler than day-precise accrual (daily_balance) — the docblock states this explicitly so future maintainers don't 'fix' it into a compound formula"

key-files:
  created: []
  modified:
    - app/Services/RevolvingCreditCalculator.php
    - app/Enums/InterestCalculationMethod.php
    - tests/Unit/RevolvingCreditCalculatorTest.php
    - tests/Unit/CreditCardDailyBalanceTest.php

key-decisions:
  - "Used a flat /12 split (annualRatePercent / 100 / 12) rather than a compound monthly-equivalent rate, per the plan's explicit instruction — direct_monthly's contract is 'applied directly each month', a deliberate simplification distinct from daily_balance's day-precise accrual"
  - "Both enum cases and all API validation (Rule::in(['daily_balance', 'direct_monthly'])) were left untouched — direct_monthly remains a valid, documented, selectable option, only its math changed"

patterns-established: []

requirements-completed: [D-01]

# Metrics
duration: ~20min
completed: 2026-08-08
---

# Phase 19 Plan 04: Direct-Monthly Interest Formula Correction Summary

**Fixed `RevolvingCreditCalculator::calculateInterestDirectMonthly()` to charge a flat twelfth of the annual rate per month (`annualRatePercent / 100 / 12`) instead of applying the full annual rate as a single month's charge (~12x too high), and corrected every stale test assertion — across both `RevolvingCreditCalculatorTest.php` and `CreditCardDailyBalanceTest.php` — that previously pinned the buggy ~75.88 figure as "expected".**

## Performance

- **Duration:** ~20 min
- **Completed:** 2026-08-08
- **Tasks:** 2 completed
- **Files modified:** 4

## Accomplishments

- `calculateInterestDirectMonthly(600.00, 14.0)` now returns exactly `7.0` (was `75.88` before this plan), with the guard clauses (non-positive balance or rate) still returning `0.0`, all pinned by exact `assertSame` assertions.
- `InterestCalculationMethod::DIRECT_MONTHLY`'s `getDescription()` text now states the corrected formula (`balance × (annual_rate / 100 / 12)`); both enum cases and the `Rule::in(['daily_balance', 'direct_monthly'])` validation in both `StoreCreditCardRequest`/`UpdateCreditCardRequest` remain untouched — `direct_monthly` stays a valid, documented, selectable API option.
- Eight test methods across two files (five `direct_monthly`-related plus three unrelated methods that happened to share the real `542` fixture) were corrected to synthetic `600`/`7.00`/`4.60`/`7.13` figures, with all real-statement provenance comments (`542`, `75.88`, `4.16`, "User's real case", "User confirmed", etc.) removed per D-04.
- The no-longer-true `daily_balance_and_direct_monthly_produce_different_results` directional assertion (`monthly > daily`) was replaced with exact expected values (`7.13` daily over 31 days, `7.0` monthly) rather than being weakened or deleted.
- `it_validates_user_bank_statement_14_percent` was renamed to `daily_balance_interest_over_a_31_day_cycle_at_14_percent`; `daily_balance_interest_is_lower_than_direct_monthly_rate` was renamed to `daily_balance_interest_over_20_days_is_lower_than_the_flat_monthly_rate`, and both had their real-case narrative comments deleted.

## Task Commits

1. **Task 1: Replace the direct-monthly formula with a flat twelfth of the annual rate** - `0a9a257` (fix)
2. **Task 2: Correct every stale direct_monthly assertion and strip real-statement provenance from the touched tests** - `590795d` (test)

**Plan metadata:** committed separately by the orchestrator after wave completion (worktree mode — STATE.md/ROADMAP.md are not touched by this agent)

## Files Created/Modified

- `app/Services/RevolvingCreditCalculator.php` - `calculateInterestDirectMonthly()` return statement changed from `round($currentBalance * ($annualRatePercent / 100), 2)` to `round($currentBalance * ($annualRatePercent / 100 / 12), 2)`; docblock rewritten to state the corrected formula and explain the deliberate flat-rate simplification.
- `app/Enums/InterestCalculationMethod.php` - `DIRECT_MONTHLY` case's `getDescription()` text updated to `'Interest = balance × (annual_rate / 100 / 12) — a flat twelfth of the annual rate, applied directly each month'`.
- `tests/Unit/RevolvingCreditCalculatorTest.php` - Corrected `it_calculates_interest_using_direct_monthly_method` (added guard-clause assertions), `it_uses_direct_monthly_method_when_configured`, `daily_balance_and_direct_monthly_produce_different_results` (exact values replace the directional assertion), renamed and rewrote `it_validates_user_bank_statement_14_percent` → `daily_balance_interest_over_a_31_day_cycle_at_14_percent`, and updated `it_calculates_daily_balances_for_a_cycle`, `it_calculates_interest_from_daily_balances`, `first_cycle_has_zero_interest` to use the synthetic `600`-based fixtures.
- `tests/Unit/CreditCardDailyBalanceTest.php` - Renamed and rewrote `daily_balance_interest_is_lower_than_direct_monthly_rate` → `daily_balance_interest_over_20_days_is_lower_than_the_flat_monthly_rate`, replacing the literal `75.88` comparison with a calculator-derived `$monthlyInterest` value.

## Decisions Made

- Followed the plan's exact formula guidance (`/ 100 / 12`, not a compound monthly-equivalent rate) — no deviation from the specified math.
- `use PHPUnit\Framework\Attributes\Test;` was already present in `tests/Unit/RevolvingCreditCalculatorTest.php` (added by an earlier, out-of-plan commit `77362a3` "fix: repair silent test-discovery gap across 10 test files") — the plan's Task 1 instruction to add it first was a no-op verified via grep rather than a fresh edit.

## Deviations from Plan

None - plan executed exactly as written. The only notable pre-condition was that the test-discovery import fix Task 1 called for was already present from a prior, unrelated repo-wide fix commit; this was verified, not re-applied.

## Issues Encountered

None specific to this plan's files. `php artisan test --testsuite=Unit` (103 tests): 101 passed, 2 failed — both are the same pre-existing, unrelated failures already documented in `deferred-items.md` by plans 19-01/19-02/19-03 (`CreditCardCreditLineSyncTest::payments_reintegrate_only_principal_on_status_changes` and `CreditCardKpiServiceTest::it_returns_expected_credit_card_kpis_for_user`), neither of which touches `RevolvingCreditCalculator.php`, `InterestCalculationMethod.php`, or either test file this plan modified. Not investigated further per the scope-boundary rule; no new deferred-items entry added since these are the exact same pre-existing failures.

## TDD Gate Compliance

Task 1 was marked `tdd="true"` in the plan, but the plan's own task split places the implementation fix (Task 1, commit `0a9a257`, type `fix`) before the test corrections (Task 2, commit `590795d`, type `test`) — the reverse of the canonical RED→GREEN commit order. This was intentional and plan-directed, not a deviation: the pre-existing tests already encoded the buggy ~75.88 figure as "expected" (i.e., they were passing against the bug), so there was no meaningful RED state to capture before the fix — writing a correct-value assertion first would have required simultaneously rewriting the same test methods Task 2 was scoped to handle. The plan explicitly told Task 1 to "fold [the guard assertions] into the existing test corrected in Task 2," confirming the code-first ordering was by design.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All four of the original D-01 causal-chain defects (billing-period generalization, payment-unaware daily-balance walk, stamp-duty-ignoring payment breakdown, and this plan's direct_monthly ~12x-too-high formula) are now fixed and covered by regression tests.
- `daily_balance` remains the default `interest_calculation_method`, so this landmine was never triggered for any card that had not explicitly opted into `direct_monthly` — this plan closes that option's remaining risk.
- The two pre-existing, unrelated `CreditCardCreditLineSyncTest`/`CreditCardKpiServiceTest` failures remain open in `deferred-items.md` for a future dedicated investigation.
- Plan 19-05 (which owns any remaining `tests/Feature/` failures per this plan's `<verification>` note) was not inspected for interaction; this plan touched only the four files declared in its `files_modified` frontmatter.

---
*Phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl*
*Completed: 2026-08-08*

## Self-Check: PASSED

- FOUND: app/Services/RevolvingCreditCalculator.php
- FOUND: app/Enums/InterestCalculationMethod.php
- FOUND: tests/Unit/RevolvingCreditCalculatorTest.php
- FOUND: tests/Unit/CreditCardDailyBalanceTest.php
- FOUND: .planning/phases/19-revolving-credit-card-interest-engine-correctness-align-cycl/19-04-SUMMARY.md
- FOUND commit: 0a9a257 (fix)
- FOUND commit: 590795d (test)
- FOUND commit: b477b71 (docs: add plan summary)
