# Deferred Items — Phase 19

Issues discovered during plan execution that are out of scope for the current plan (pre-existing failures unrelated to the plan's changes). Logged per the executor's scope-boundary rule instead of fixed.

## From Plan 19-02

- **`Tests\Unit\CreditCardCreditLineSyncTest::payments_reintegrate_only_principal_on_status_changes`** — fails both before and after this plan's changes (`assertSame(500.0, ...)` gets `0.0`). Verified against a clean `git stash` baseline of this worktree; unrelated to `CreditCardCycleService::ensureCurrentMonthCycle` or `calculateRevolvingPaymentBreakdown`. Root cause not investigated (out of scope for D-01/D-02).
- **`Tests\Unit\CreditCardKpiServiceTest::it_returns_expected_credit_card_kpis_for_user`** — fails both before and after this plan's changes (`assertSame(980.0, ...)` gets `120.0`). Same baseline verification. Root cause not investigated (out of scope for D-01/D-02).
