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
   - Re-confirmed during 19-05: `git log --oneline -1` and `git diff main` for this file
     both show zero divergence from `main`, and running the test in isolation
     (`--filter=FinanceReportPageTest`) reproduces the exact same failure with no other
     tests running. The fixture hardcodes transaction/budget dates in `2026-04`, so the
     failure is consistent with a date-dependent "current budget month" resolution now
     that the system date (`2026-08-08` at time of this wave) has moved past that fixed
     month, not a worktree asset-build gap as originally guessed by 19-01. Still unrelated
     to credit cards and out of scope for this phase; not investigated further.

## Fixed during 19-05 (not deferred)

5. `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response` — the
   worktree had no `public/build/manifest.json` (Vite assets never built here). Ran
   `npm install && npm run build` in this worktree (build output only, gitignored, not
   committed) and the test now passes. This was a worktree provisioning gap, not a code
   issue, consistent with entry 3 above.
