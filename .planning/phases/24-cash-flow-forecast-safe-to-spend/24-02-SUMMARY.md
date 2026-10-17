# 24-02 Summary — forecast arithmetic and API

`GET /api/v1/dashboard/cash-flow-forecast` (`DashboardController::cashFlowForecast`, `CashFlowForecastService`).

## Arithmetic

Per currency: `estimated_remaining = available_balance - counted_commitments`, in integer minor units, formatted as decimal strings at the boundary. No clamping (negative stays negative) and no cross-currency total.

- Available balance: owner's active accounts with a valid ISO currency, minus any account linked to an active (non-archived, non-deleted) saving goal (whole account excluded, D-03).
- Counted: sum of `cash_outflow` of window obligations; obligations paid from an excluded or inactive account stay in the breakdown with reason `source_account_excluded` and contribute 0.

## Response shape

```json
{ "data": {
  "as_of": "2026-10-17", "through_date": "2026-11-16", "horizon_days": 30,
  "currencies": [{
    "currency": "EUR", "available_balance": "1000.00", "counted_commitments": "250.00",
    "estimated_remaining": "750.00", "included_account_count": 1, "excluded_account_count": 0,
    "obligations": [{ "id": "loan:12:2026-10-25", "type": "loan", "source_id": 12, "name": "…",
      "due_date": "2026-10-25", "currency": "EUR", "face_amount": "250.00",
      "cash_outflow_amount": "250.00", "posting_state": "unposted", "reason": null, "overdue": false }],
    "overdue_obligations": [], "coverage_flags": ["bank_data_not_reconciled"], "complete": false }],
  "unresolved_obligations": [],
  "coverage": { "complete": false, "flags": [{ "code": "bank_data_not_reconciled", "count": 1 }], "inactive_account_count": 0 } } }
```

`complete` is always `false` for now: Phase 25 (bank reconciliation) does not exist, so `bank_data_not_reconciled` is raised unconditionally. Other flags: `overdue_obligations`, `posting_soft_deleted`, `card_charge_projected`, `source_account_unavailable`, `unknown_currency`. The block is self-contained for coexistence with Phase 26 dashboard totals.

## Tests

`tests/Feature/Api/CashFlowForecastApiTest.php` (13): auth, single and mixed currency, goal exclusion (and archived goal), inactive account, posted installment not deducted twice, overdue listing, never-complete flag, zero balance / negative result, empty user, unknown currency, superadmin personal scope. Full suite: 654 tests green (run with an inline synthetic `APP_KEY`).

## Notes / risks

- Accounts of type `debt` / `credit_card` are excluded from the available balance (review decision); they are reported as `coverage.non_spendable_account_count`, and obligations paid from them are not deducted.
- New flag `paid_without_posting` (see 24-01).
