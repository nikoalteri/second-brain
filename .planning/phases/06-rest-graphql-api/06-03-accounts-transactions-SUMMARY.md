---
phase: "06"
plan: "03"
subsystem: api
tags: [accounts, transactions, rest, querybuilder, resources, form-requests]
dependency_graph:
  requires: ["06-01 (sanctum/lighthouse/spatie packages)", "06-02 (AuthController, routes/api.php skeleton)"]
  provides: ["AccountController CRUD", "TransactionController CRUD", "AccountResource", "TransactionResource"]
  affects: ["routes/api.php (consumers)", "Account model", "Transaction model"]
tech_stack:
  added: []
  patterns: ["spatie/laravel-query-builder cursor pagination", "Form Request authorization delegation to Policy", "JsonResource toArray", "Eloquent local scopes for date filtering"]
key_files:
  created:
    - app/Http/Controllers/Api/V1/AccountController.php
    - app/Http/Controllers/Api/V1/TransactionController.php
    - app/Http/Resources/Api/AccountResource.php
    - app/Http/Resources/Api/TransactionResource.php
    - app/Http/Requests/Api/StoreAccountRequest.php
    - app/Http/Requests/Api/UpdateAccountRequest.php
    - app/Http/Requests/Api/StoreTransactionRequest.php
    - app/Http/Requests/Api/UpdateTransactionRequest.php
  modified:
    - app/Models/Transaction.php
decisions:
  - "Used explicit ->where('user_id', ...) scoping (no HasUserScoping trait on these models)"
  - "cursorPaginate used for both controllers (efficient for large datasets)"
  - "TransactionResource loads account and category via whenLoaded() — avoids N+1 while remaining optional"
  - "balance initialized to opening_balance in AccountController::store()"
metrics:
  duration: "~5 minutes"
  completed: "2025-07-09"
  tasks: 2
  files: 9
---

# Phase 6 Plan 3: Accounts & Transactions REST Controllers Summary

## One-liner
AccountController and TransactionController with user-scoped QueryBuilder queries, cursor pagination, exact/scope filters, and typed JSON resources.

## What Was Built

### Task T1 — AccountController + AccountResource + Account Form Requests
- `AccountController`: full CRUD (index/store/show/update/destroy) with `QueryBuilder::for(Account::class)->where('user_id', ...)`, filters on `type`, `is_active`, `currency`, sorts on `name/balance/opening_balance/created_at`, cursor pagination
- `AccountResource`: typed JSON shape with `(float)` balance/opening_balance, `(bool)` is_active, ISO timestamps
- `StoreAccountRequest`: required name/type/currency, nullable opening_balance, optional is_active
- `UpdateAccountRequest`: all fields `sometimes` for partial PATCH support

### Task T2 — TransactionController + TransactionResource + Transaction Form Requests
- `TransactionController`: full CRUD with `->where('user_id', ...)`, eager-loads `account` + `category`, filters on `account_id`, `transaction_category_id`, `date_from`/`date_to` scopes, `is_transfer`, sorts on `date/amount/created_at/description`
- `TransactionResource`: typed JSON shape with `whenLoaded('account')` and `whenLoaded('category')` inline embeds
- `StoreTransactionRequest`: full validation including `different:account_id` for to_account_id
- `UpdateTransactionRequest`: all fields `sometimes` for partial updates
- `Transaction::scopeDateFrom` / `Transaction::scopeDateTo` added to model for date range filter

## Verification Results

```
php artisan inspire  → EXIT 0 (no parse errors)
php artisan test --no-coverage → 110 passed (254 assertions) — Duration: 3.16s
```

## Deviations from Plan

None — plan executed exactly as written.

## Known Stubs

None. Controllers delegate authorization to Policies (not yet created in this phase — Policy stubs exist from earlier phases or will be created in plan 06-04).

## Self-Check: PASSED

- All 9 files exist and are committed
- 110/110 tests passing
- `php artisan inspire` exits 0
