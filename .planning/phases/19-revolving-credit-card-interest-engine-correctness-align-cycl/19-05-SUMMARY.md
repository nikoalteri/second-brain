---
phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl
plan: 05
subsystem: billing
tags: [laravel, credit-cards, billing-cycles, phpunit, regression]

# Dependency graph
requires:
  - phase: 19-01
    provides: "fixed_payment_includes_stamp_duty flag wired end-to-end"
  - phase: 19-02
    provides: "ensureCurrentMonthCycle() period anchoring off the previous cycle's statement_date"
  - phase: 19-03
    provides: "payment-aware daily-balance walk and stamp-duty-branch calculatePaymentBreakdown()"
  - phase: 19-04
    provides: "corrected calculateInterestDirectMonthly() flat-twelfth formula"
provides:
  - "End-to-end feature regression (RevolvingInterestEngineRegressionTest) proving D-02 period derivation and both D-03 stamp-duty modes compose correctly through the real CreditCardCycleService::ensureCurrentMonthCycle + issueCycle flow"
  - "Confirmation that CreditCardLifecycleIntegrationTest's direct_monthly expectations were already corrected (by 19-04's own scope-fix commit dd9bdc0), closing the phase's last planned assertion correction"
affects: []

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Feature-level regression tests drive the real service (CreditCardCycleService, not the calculator directly) across two consecutive cycles to prove period-derivation and payment-breakdown composition, matching the existing CreditCardLifecycleIntegrationTest conventions"

key-files:
  created:
    - tests/Feature/RevolvingInterestEngineRegressionTest.php
  modified:
    - .planning/phases/19-revolving-credit-card-interest-engine-correctness-align-cycl/deferred-items.md

key-decisions:
  - "Task 1 (correcting direct_monthly expectations in CreditCardLifecycleIntegrationTest) required no code change: commit dd9bdc0 from plan 19-04's own deviation handling ('fix(19-04): correct stale direct_monthly assertion missed by plan scope') had already applied the exact assertions this plan's Task 1 specified (11.0 / 239.0 / 861.0). Verified via grep against all six acceptance-criteria patterns before concluding no work remained."
  - "Committed the new regression test as type `test`, not `feat`, because all three tests passed on the first run with zero production-code changes — the feature these tests prove already exists, fixed by plans 19-01 through 19-04. This mirrors the fail-fast/investigate guidance for a passing 'RED' state: investigated (read CreditCardCycleService.php and RevolvingCreditCalculator.php, hand-derived every expected figure) and confirmed the pass is correct, not a masked bug."
  - "Built frontend assets (npm install && npm run build) in this worktree to fix Tests\\Feature\\ExampleTest, which was failing only due to a missing Vite manifest in this fresh worktree — an environment provisioning gap, not a code issue. This is in-scope under Rule 3 (blocking issue preventing a clean full-suite verification run) and was reverted from git (build output and package-lock.json name field are gitignored/not committed)."
  - "Left three failures deferred and unfixed, consistent with the precedent set by plans 19-01 through 19-04: CreditCardCreditLineSyncTest and CreditCardKpiServiceTest (Unit tests, not touched by any Phase 19 plan, reproduce identically on an unmodified main baseline) and FinanceReportPageTest (Filament admin budget-report test, unrelated to credit cards, byte-identical to main, and re-confirmed in isolation to fail independently of the other three). None of these files were ever in any Phase 19 plan's files_modified list, so the plan's own 'fix it here if owned by an earlier plan in this phase' instruction does not apply to them."

patterns-established: []

requirements-completed: [D-01, D-02, D-03, D-04]

# Metrics
duration: ~50min
completed: 2026-08-08
---

# Phase 19 Plan 05: End-to-End Regression & Phase Closeout Summary

**Added `tests/Feature/RevolvingInterestEngineRegressionTest.php`, a three-test synthetic regression that drives the real `CreditCardCycleService` through two consecutive cycles to prove D-02's period-after-previous-statement-date derivation and both D-03 stamp-duty-inclusion modes compose correctly end to end — all three tests passed immediately, confirming plans 19-01 through 19-04 already closed every defect correctly.**

## Performance

- **Duration:** ~50 min
- **Started:** 2026-08-08T14:05:00Z (approx)
- **Completed:** 2026-08-08T14:54:34Z
- **Tasks:** 2 (Task 1 required no code change; Task 2 completed with one commit)
- **Files modified:** 2 (1 created, 1 modified)

## Accomplishments

- Confirmed Task 1's target assertions (`interest_amount` 11.0, `principal_amount` 239.0, post-payment `current_balance` 861.0) were already present in `tests/Feature/CreditCardLifecycleIntegrationTest.php`, applied by plan 19-04's own deviation-handling commit `dd9bdc0`. No forbidden figures (`132.0`, `118.0`, `982.0`) remain; the `direct_monthly` fixture is retained exactly as required.
- Added `RevolvingInterestEngineRegressionTest` with a shared `makeCardWithPaidFirstCycle()` fixture helper and three tests:
  - `second_cycle_period_starts_the_day_after_the_previous_statement_date` — proves cycle B's `period_start_date` is `2027-04-07` (the day after cycle A's `2027-04-06` statement date), `period_month` is `2027-05`, and repeated `ensureCurrentMonthCycle()` calls stay idempotent (`CreditCardCycle::count() === 2`).
  - `inclusive_stamp_duty_card_bills_total_due_equal_to_its_fixed_payment` — proves the inclusive-mode split (`interest_amount` 6.52, `principal_amount` 241.48, `total_due` 250.0) and that marking the issued payment PAID reduces `current_balance` to exactly `758.52` (1000 − 241.48, principal only).
  - `exclusive_stamp_duty_card_bills_fixed_payment_plus_duty` — proves the exclusive-mode split (`principal_amount` 243.48, `total_due` 252.0) stays unchanged from today's default behavior.
- All three tests passed on the first run with zero production-code changes, and the full suite (`php artisan test`) was driven to only the same three pre-existing, out-of-scope failures already documented by earlier waves — one previously-failing test (`ExampleTest`) was additionally fixed by building frontend assets in this worktree.
- Repo-wide grep gate for every real-statement figure (`75.88`, `1183.30`, `1909.98`, `233.93`, `14.07`, `21.98`, `542`, `692`, `642`, `4.16`, `230.28`, `311.72`) across `tests/`, `app/`, `database/` returns zero matches.

## Task Commits

1. **Task 1: Correct the direct_monthly expectations in the lifecycle integration test** — no commit needed; already satisfied by plan 19-04's commit `dd9bdc0` (verified via grep, see Decisions Made)
2. **Task 2: Add the end-to-end synthetic regression test and take the full suite green** — `1098d60` (test)

**Plan metadata:** committed separately by the orchestrator after wave completion (worktree mode — STATE.md/ROADMAP.md are not touched by this agent)

## Files Created/Modified

- `tests/Feature/RevolvingInterestEngineRegressionTest.php` — New feature regression test, three `#[Test]` methods plus a private `makeCardWithPaidFirstCycle(bool $includesStampDuty)` fixture helper, using only a fictional 2027-dated `Fixture Revolving Card` (no real statement figures or provenance comments per D-04).
- `.planning/phases/19-revolving-credit-card-interest-engine-correctness-align-cycl/deferred-items.md` — Added a re-confirmation note for the `FinanceReportPageTest` failure (now attributed to a date-dependent fixture rather than the originally-guessed worktree asset-build gap) and a new "Fixed during 19-05" entry documenting that `ExampleTest` was resolved by building frontend assets in this worktree.

## Decisions Made

See `key-decisions` in the frontmatter for full rationale on: (1) Task 1 requiring no code change, (2) committing the new test as `test` rather than `feat`, (3) building frontend assets as an in-scope Rule 3 fix, and (4) leaving three pre-existing, phase-19-unrelated failures deferred.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Worktree had no `vendor/`, no `.env`, and no built frontend assets**
- **Found during:** Environment setup, before Task 1
- **Issue:** This worktree started with no `vendor/` directory at all (not a stale symlink) and no `.env`. Additionally, `public/build/manifest.json` did not exist, which fails `Tests\Feature\ExampleTest` (a Vite-manifest-dependent smoke test) during any full-suite run needed for this plan's `<verification>` gate.
- **Fix:** Ran `composer install` (vendor is gitignored, not committed), copied `.env.example` to `.env` and ran `php artisan key:generate --force` (both gitignored, not committed), created `database/database.sqlite`, and ran `npm install && npm run build` to produce `public/build/` (gitignored, not committed). Reverted an incidental `package-lock.json` diff (only a cosmetic `name` field mismatch from running `npm install` in a differently-named worktree directory) with `git checkout -- package-lock.json` before committing, since it carried no functional change.
- **Files modified:** None tracked by git.
- **Verification:** `php artisan test --filter=ExampleTest` passes after the build step; full suite dropped from 4 failures to 3.
- **Committed in:** N/A (environment-only fix, no commit)

---

**Total deviations:** 1 auto-fixed (1 blocking, environment-only). No application-code or test-fixture deviations — both application-facing outcomes (Task 1's already-correct assertions, Task 2's new regression test) match the plan exactly with zero rework.

**Impact on plan:** None on scope. Both deviations were pre-existing worktree provisioning gaps discovered while verifying this plan's own `<verification>` gates, not consequences of any code change in this plan.

## Issues Encountered

- **Three pre-existing, out-of-scope test failures remain in the full suite** (`php artisan test`: 279 tests, 1072 assertions, 3 failures), all previously documented in `deferred-items.md` by earlier waves of this phase and reconfirmed here as identical and unrelated to any Phase 19 file:
  - `Tests\Unit\CreditCardCreditLineSyncTest::payments_reintegrate_only_principal_on_status_changes` — `Failed asserting that 0.0 is identical to 500.0.` at `tests/Unit/CreditCardCreditLineSyncTest.php:93`. Never touched by any Phase 19 plan.
  - `Tests\Unit\CreditCardKpiServiceTest::it_returns_expected_credit_card_kpis_for_user` — `Failed asserting that 120.0 is identical to 980.0.` at `tests/Unit/CreditCardKpiServiceTest.php:124`. Never touched by any Phase 19 plan.
  - `Tests\Feature\Filament\FinanceReportPageTest::test_admin_finance_report_renders_budget_month_context_alerts_and_export_labels` — `assertSee('exceeded')` fails against the rendered HTML. The test file is byte-identical to `main` (`git diff main -- tests/Feature/Filament/FinanceReportPageTest.php` returns nothing) and reproduces the exact same failure when run in isolation (`--filter=FinanceReportPageTest`, unaffected by any other test's state). Unrelated to credit cards; the fixture's hardcoded `2026-04` dates are consistent with a date-dependent "current budget month" resolution now that the system date has moved past that month — not investigated further as it is out of scope for this phase.
  - None of these three files appear in any Phase 19 plan's `files_modified` frontmatter (19-01 through 19-05), so per this plan's own instruction ("If a failure lands in a file owned by an earlier plan in this phase, fix it here") they are correctly out of scope and were left deferred rather than fixed, per the scope-boundary rule.
- Resolved one previously-deferred item: `Tests\Feature\ExampleTest` now passes after building frontend assets in this worktree (see Deviations above).

## TDD Gate Compliance

Task 2 was marked `tdd="true"`, but all three new tests in `RevolvingInterestEngineRegressionTest.php` passed on their very first run with zero production-code changes. Per the fail-fast rule ("if a test passes unexpectedly during RED, STOP and investigate — the feature may already exist"), this was investigated before proceeding: `app/Services/CreditCardCycleService.php` and `app/Services/RevolvingCreditCalculator.php` were read in full, and every expected figure in the test (period dates, `6.52` interest, `241.48`/`243.48` principal splits, `758.52` post-payment balance) was hand-derived from the current, already-corrected formulas before writing the test. The pass is not a masked bug — it is exactly this plan's stated purpose: proving that plans 19-01 through 19-04 already compose correctly end to end. The test file was committed with type `test` (not `feat`/`fix`), accurately reflecting that no implementation code changed.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness

- All four D-01–D-04 requirements for Phase 19 are now proven end to end through the real `CreditCardCycleService` flow, closing the phase.
- The repo-wide grep gate for every real Amex statement figure returns zero matches across `tests/`, `app/`, `database/`.
- Three pre-existing, unrelated test failures remain open in `deferred-items.md` for a future dedicated investigation phase (`CreditCardCreditLineSyncTest`, `CreditCardKpiServiceTest`, `FinanceReportPageTest`) — none touch any file this phase modified.
- This worktree's `vendor/`, `.env`, `database/database.sqlite`, and `public/build/` are all local, gitignored artifacts required to run the suite; a fresh worktree for any follow-up phase will need the same provisioning steps documented in this and prior plans' Deviations sections.

---
*Phase: 19-revolving-credit-card-interest-engine-correctness-align-cycl*
*Completed: 2026-08-08*

## Self-Check: PASSED

- FOUND: tests/Feature/RevolvingInterestEngineRegressionTest.php
- FOUND: .planning/phases/19-revolving-credit-card-interest-engine-correctness-align-cycl/19-05-SUMMARY.md
- FOUND commit: 1098d60 (test)
- FOUND commit: 5702d81 (docs: add plan summary)
- No missing items
