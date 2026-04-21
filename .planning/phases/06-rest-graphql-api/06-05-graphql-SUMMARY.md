---
phase: "06"
plan: "05"
subsystem: graphql-api
tags: [graphql, lighthouse, schema, resolvers, user-scoping]
dependency_graph:
  requires: [06-01, 06-02, 06-03, 06-04]
  provides: [graphql-schema, aggregate-resolvers, user-scoped-models]
  affects: [Account, Transaction, Loan, CreditCard, Subscription]
tech_stack:
  added: [nuwave/lighthouse@6.66.0]
  patterns: [GlobalScope via HasUserScoping, custom field resolvers, Lighthouse @paginate @guard @create @update @delete @inject]
key_files:
  created:
    - graphql/schema.graphql (full finance schema)
    - app/GraphQL/Queries/MonthlyCashflow.php
    - app/GraphQL/Queries/TotalByCategory.php
  modified:
    - app/Models/Account.php
    - app/Models/Transaction.php
    - app/Models/Loan.php
    - app/Models/CreditCard.php
    - app/Models/Subscription.php
decisions:
  - "Renamed type Subscription to ServiceSubscription: avoids conflict with Lighthouse built-in GraphQL Subscription root type"
  - "Removed @scope directive from list queries: Lighthouse 6.66 @scope is ARGUMENT_DEFINITION only, not FIELD_DEFINITION; replaced with HasUserScoping global scope on all models"
  - "Removed @with from transactions query: Lighthouse disallows @with on root Query fields; @belongsTo/@hasMany handle N+1 via batch loading on nested field resolvers"
  - "HasUserScoping added to Account, Transaction, Loan, CreditCard: ensures all GraphQL paginated queries are user-scoped automatically when authenticated"
metrics:
  duration: "~15 minutes"
  completed: "2026-04-21"
  tasks_completed: 4
  files_changed: 8
---

# Phase 6 Plan 5: GraphQL Schema — Finance Types, Queries, Mutations, Resolvers Summary

**One-liner:** Full Lighthouse GraphQL schema with 7 finance types, 12 guarded queries, 15 auth-injected mutations, and custom income/expense aggregate resolvers.

## What Was Built

### T1 — Schema: Finance Types + Queries

`graphql/schema.graphql` was fully replaced with a production-ready Lighthouse schema:

**Types (7 domain + User):**
- `Account` — bank accounts with `transactions: [Transaction!]! @hasMany`
- `Transaction` — financial transactions with `account @belongsTo` and `category @belongsTo`
- `TransactionCategory` — spending categories
- `Loan` / `LoanPayment` — loan amortization with `payments @hasMany`
- `CreditCard` / `CreditCardCycle` / `CreditCardExpense` — revolving/charge cards
- `ServiceSubscription` — recurring subscriptions (renamed from `Subscription`)
- `MonthlyCashflow` — aggregate income/expense summary
- `CategoryTotal` — per-category spending totals

**Queries (12):**
All guarded with `@guard` (Sanctum Bearer token required):
- `accounts`, `account` — filter by `is_active`, `type`
- `transactions`, `transaction` — filter by `account_id`, `is_transfer`
- `loans`, `loan` — filter by `status`
- `creditCards`, `creditCard` — filter by `status`, `type`
- `subscriptions`, `subscription` — filter by `status`, `frequency`
- `monthlyCashflow(year, month)` — custom resolver
- `totalByCategory(year, month)` — custom resolver

### T2 — Mutations + Input Types

**Mutations (15):**
All CRUD for all 5 domains with `@create @guard @inject(context: "user.id", name: "user_id")` for automatic user_id injection on creation.

**Input Types (10):** `CreateAccountInput`, `UpdateAccountInput`, `CreateTransactionInput`, `UpdateTransactionInput`, `CreateLoanInput`, `UpdateLoanInput`, `CreateCreditCardInput`, `UpdateCreditCardInput`, `CreateSubscriptionInput`, `UpdateSubscriptionInput`

**Custom Resolvers:**
- `MonthlyCashflow` — filters by `user_id`, loads `type` relation, sums by `TransactionType.is_income`
- `TotalByCategory` — raw SQL with `LEFT JOIN transaction_categories`, groups by category, respects soft deletes

### T2b — scopeBelongsToAuthUser + HasUserScoping

All 5 models now have:
1. `scopeBelongsToAuthUser($query)` — explicit scope for controller-level use
2. `HasUserScoping` trait — global scope filtering by `auth()->id()` when authenticated (used by GraphQL layer)

Previously only `Subscription` had `HasUserScoping`. Account, Transaction, Loan, CreditCard now added.

### T3 — Validation

- `php artisan lighthouse:validate-schema` → **"The defined schema is valid."**
- `php artisan test --no-coverage` → **110 tests, 254 assertions — all pass**

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Removed `@with` from transactions query**
- **Found during:** T3 schema validation
- **Issue:** Lighthouse 6 disallows `@with` on root Query/Mutation type fields; throws `DefinitionException: Can not use @with on fields of a root type`
- **Fix:** Removed `@with(relation: ["account", "category"])` from `transactions` query. N+1 prevention is handled automatically by `@belongsTo`/`@hasMany` batch loaders on the nested field resolvers
- **Files modified:** `graphql/schema.graphql`

**2. [Rule 1 - Bug] Renamed `type Subscription` to `type ServiceSubscription`**
- **Found during:** T3 schema validation (after @with fix)
- **Issue:** Lighthouse 6 treats `type Subscription` as the GraphQL subscriptions root type, throwing `Exception: Register the SubscriptionServiceProvider to enable subscriptions`
- **Fix:** Renamed type to `ServiceSubscription`; added `model: "Subscription"` argument to `@paginate`, `@find`, `@create`, `@update`, `@delete` directives so Lighthouse resolves the correct model
- **Files modified:** `graphql/schema.graphql`

**3. [Rule 1 - Bug] Replaced `@scope(name: "belongsToAuthUser")` with `HasUserScoping` global scope**
- **Found during:** T3 schema validation (after Subscription rename fix)
- **Issue:** In Lighthouse 6.66.0 the `@scope` directive is only valid at `ARGUMENT_DEFINITION` / `INPUT_FIELD_DEFINITION` locations, not `FIELD_DEFINITION`. Error: "Directive @scope not allowed at FIELD_DEFINITION location."
- **Fix:** Removed `@scope` from all 5 list queries. Added `HasUserScoping` trait (which provides a global scope `where('user_id', auth()->id())` only when authenticated) to Account, Transaction, Loan, CreditCard. Subscription already used this trait
- **Files modified:** `graphql/schema.graphql`, `app/Models/Account.php`, `app/Models/Transaction.php`, `app/Models/Loan.php`, `app/Models/CreditCard.php`

## Known Stubs

None — all resolvers are fully implemented with real database queries.

## Self-Check

## Self-Check: PASSED

| Check | Result |
|-------|--------|
| `graphql/schema.graphql` exists | ✅ FOUND |
| `app/GraphQL/Queries/MonthlyCashflow.php` exists | ✅ FOUND |
| `app/GraphQL/Queries/TotalByCategory.php` exists | ✅ FOUND |
| Commit `3e3695f` exists | ✅ FOUND |
| All 5 models have `scopeBelongsToAuthUser` | ✅ FOUND |
| `HasUserScoping` on Account, Transaction, Loan, CreditCard | ✅ FOUND |
| `php artisan lighthouse:validate-schema` | ✅ PASSED |
| 110 tests passing | ✅ PASSED |
