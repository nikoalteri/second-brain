# Deferred Items — Phase 19

## Pre-existing test failures (out of scope for 19-01)

Discovered while running `php artisan test --testsuite=Unit` to verify plan 19-01 did not
regress anything. Both failures reproduce identically on the unmodified `main` branch
baseline (commit `f627aaf`), confirmed by running the same filters directly against the
main repo checkout. Not caused by 19-01's changes (which only add the
`fixed_payment_includes_stamp_duty` column/cast/fillable/factory default). Left unfixed
per the scope boundary rule — plans 19-02 through 19-05 target the underlying
`CreditCardCycleService` / KPI calculation logic these tests exercise.

1. `Tests\Unit\CreditCardCreditLineSyncTest::payments reintegrate on...`
   - `tests/Unit/CreditCardCreditLineSyncTest.php:93`
   - `Failed asserting that 0.0 is identical to 500.0.`

2. `Tests\Unit\CreditCardKpiServiceTest::it returns expected credit...`
   - `tests/Unit/CreditCardKpiServiceTest.php:124`
   - `Failed asserting that 120.0 is identical to 980.0.`

## Worktree environment gaps (not code issues)

Discovered running the wider Feature suite for regression-checking 19-01. Unrelated to
credit cards or this plan's files.

3. `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response`
   - Fails only in this worktree with `Vite manifest not found at
     .../public/build/manifest.json` — the frontend has not been built in this worktree
     (`npm run build` was never run here). Passes on the `main` checkout, which has a
     built `public/build`. Not a code regression; a worktree asset-build gap.

4. `Tests\Feature\Filament\FinanceReportPageTest::admin finance report renders budget
   month context alerts and export...`
   - Fails only in this worktree (`assertSee('exceeded')` not found in rendered HTML);
     passes identically on the unmodified `main` checkout with the exact same test file
     (`git diff` shows zero difference for this file). Unrelated to credit cards; not
     investigated further as it is out of scope for 19-01 and reproduces on an unrelated,
     unmodified admin budget-report page.
