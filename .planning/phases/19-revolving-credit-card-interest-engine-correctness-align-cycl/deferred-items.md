# Deferred Items — Phase 19

Issues discovered during plan execution that are out of scope for the current plan (pre-existing failures unrelated to the plan's changes). Logged per the executor's scope-boundary rule instead of fixed.

## Pre-existing test failures (out of scope for 19-01 and 19-02)

Discovered independently by both 19-01 and 19-02 while regression-testing against the wider `Unit` suite. Both failures reproduce identically on the unmodified `main` branch baseline (commit `f627aaf`), confirmed by both plans running the same filters directly against a clean checkout/stash. Not caused by either plan's changes (19-01 only adds the `fixed_payment_includes_stamp_duty` column/cast/fillable/factory default; 19-02 only touches `CreditCardCycleService::ensureCurrentMonthCycle` and `calculateRevolvingPaymentBreakdown`'s rate divisor). Left unfixed per the scope boundary rule — this is the same finding the orchestrator already flagged separately for a future dedicated investigation phase.

1. `Tests\Unit\CreditCardCreditLineSyncTest::payments_reintegrate_only_principal_on_status_changes`
   - `tests/Unit/CreditCardCreditLineSyncTest.php:93`
   - `Failed asserting that 0.0 is identical to 500.0.`

2. `Tests\Unit\CreditCardKpiServiceTest::it_returns_expected_credit_card_kpis_for_user`
   - `tests/Unit/CreditCardKpiServiceTest.php:124`
   - `Failed asserting that 120.0 is identical to 980.0.`

## Worktree environment gaps (not code issues)

Discovered by 19-01 running the wider Feature suite for regression-checking. Unrelated to credit cards or this phase's scope.

3. `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response`
   - Fails only in worktree isolation with `Vite manifest not found at
     .../public/build/manifest.json` — the frontend has not been built in that worktree
     (`npm run build` was never run there). Passes on the `main` checkout, which has a
     built `public/build`. Not a code regression; a worktree asset-build gap.

4. `Tests\Feature\Filament\FinanceReportPageTest::admin finance report renders budget
   month context alerts and export...`
   - Fails only in worktree isolation (`assertSee('exceeded')` not found in rendered HTML);
     passes identically on the unmodified `main` checkout with the exact same test file
     (`git diff` shows zero difference for this file). Unrelated to credit cards; not
     investigated further as it is out of scope for this phase and reproduces on an
     unrelated, unmodified admin budget-report page.
