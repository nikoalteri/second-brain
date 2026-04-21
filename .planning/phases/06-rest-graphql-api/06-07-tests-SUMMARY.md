---
phase: "06"
plan: "07"
subsystem: "api-tests"
tags: [testing, rest-api, graphql, feature-tests, sanctum, spatie-query-builder]
dependency_graph:
  requires: ["06-01", "06-02", "06-03", "06-04", "06-05", "06-06"]
  provides: ["full-api-test-coverage"]
  affects: ["ci-pipeline"]
tech_stack:
  added: []
  patterns:
    - "RefreshDatabase + factory setup before Sanctum::actingAs to avoid HasUserScoping override"
    - "withoutGlobalScopes() for raw aggregate queries"
    - "Variadic Spatie QueryBuilder args (allowedFilters/allowedSorts)"
key_files:
  created:
    - "tests/Feature/Api/AuthApiTest.php"
    - "tests/Feature/Api/AccountApiTest.php"
    - "tests/Feature/Api/LoanApiTest.php"
    - "tests/Feature/Api/CreditCardApiTest.php"
    - "tests/Feature/Api/SubscriptionApiTest.php"
    - "tests/Feature/Api/TransactionApiTest.php"
    - "tests/Feature/Api/GraphQLApiTest.php"
  modified:
    - "app/Models/User.php"
    - "app/Http/Controllers/Controller.php"
    - "app/Http/Controllers/Api/V1/AccountController.php"
    - "app/Http/Controllers/Api/V1/TransactionController.php"
    - "app/Http/Controllers/Api/V1/LoanController.php"
    - "app/Http/Controllers/Api/V1/CreditCardController.php"
    - "app/Http/Controllers/Api/V1/SubscriptionController.php"
    - "app/GraphQL/Queries/TotalByCategory.php"
    - "bootstrap/app.php"
key_decisions:
  - "Cross-user access returns 404 (not 403) because HasUserScoping global scope filters records at route model binding"
  - "Store() methods use JsonResponse return type since resource()->response() returns JsonResponse, not Response"
  - "TotalByCategory uses withoutGlobalScopes() to avoid table-alias conflicts in aggregate queries"
  - "Logout test verifies DB token deletion rather than subsequent 401 (Sanctum token caching in test context)"
metrics:
  duration: "~35 minutes"
  completed_date: "2026-04-21"
  tasks_completed: 4
  files_modified: 16
---

# Phase 6 Plan 7: REST + GraphQL API Feature Tests Summary

**One-liner:** 38 new feature tests verifying full REST/GraphQL API with 6 auto-fixed blocking bugs.

## What Was Built

Created 7 feature test files in `tests/Feature/Api/` covering the complete Phase 6 REST and GraphQL API surface:

| Test File              | Tests | Coverage                                          |
|------------------------|-------|---------------------------------------------------|
| `AuthApiTest.php`      | 9     | Login, refresh, logout, 401/403/404/422 responses |
| `AccountApiTest.php`   | 9     | CRUD, scoping, filtering, sorting, pagination     |
| `LoanApiTest.php`      | 5     | Scoping, payments relation, status filter, create |
| `CreditCardApiTest.php`| 3     | Scoping, cycles relation, cross-user 404          |
| `SubscriptionApiTest.php` | 2  | Scoping, status filter                            |
| `TransactionApiTest.php` | 3   | Scoping, date_from filter, account relation       |
| `GraphQLApiTest.php`   | 7     | Auth guard, CRUD mutation, custom resolvers, introspection |
| **Total**              | **38**| |

**Previous test count:** 110  
**New total:** 148 tests passing (413 assertions)

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 2 - Bug] User model missing `HasApiTokens` trait**
- **Found during:** Task T1 (AuthApiTest)
- **Issue:** `User` model lacked `Laravel\Sanctum\HasApiTokens`. All token operations (login, logout, refresh) threw `BadMethodCallException: Call to undefined method User::tokens()`
- **Fix:** Added `use HasApiTokens` to `app/Models/User.php`
- **Files modified:** `app/Models/User.php`
- **Commit:** 7e9e427

**2. [Rule 2 - Bug] Base Controller missing `AuthorizesRequests` trait**
- **Found during:** Task T2a (AccountApiTest)
- **Issue:** `app/Http/Controllers/Controller.php` had no traits. All `$this->authorize()` calls in every controller threw `Call to undefined method`
- **Fix:** Added `use AuthorizesRequests` to base Controller
- **Files modified:** `app/Http/Controllers/Controller.php`
- **Commit:** 7e9e427

**3. [Rule 1 - Bug] Spatie QueryBuilder variadic args mismatch**
- **Found during:** Task T2a (AccountApiTest) — logout test caused 500 when controller ran
- **Issue:** All 5 controllers called `->allowedFilters([...array...])` and `->allowedSorts([...array...])`. Spatie QueryBuilder v6+ signature is variadic: `allowedFilters(AllowedFilter|string ...$filters)`. Passing an array caused `TypeError: Argument #1 must be of type AllowedFilter|string, array given`
- **Fix:** Changed all 5 controllers to use variadic syntax: `->allowedFilters(Filter1, Filter2, ...)`
- **Files modified:** All 5 Api/V1 controllers
- **Commit:** 7e9e427

**4. [Rule 1 - Bug] Wrong return types on `store()` methods**
- **Found during:** Task T2a (AccountApiTest) — store returned 500
- **Issue:** All 5 controllers declared `store(): Response` but returned `(new Resource($model))->response()->setStatusCode(201)` which is `JsonResponse`. PHP strict type checking failed since `JsonResponse` and `Response` are siblings (both extend Symfony's base Response), not parent-child.
- **Fix:** Changed `store()` return type to `JsonResponse` in all 5 controllers, added `use Illuminate\Http\JsonResponse` imports
- **Files modified:** All 5 Api/V1 controllers
- **Commit:** 7e9e427

**5. [Rule 1 - Bug] `bootstrap/app.php` exception handlers targeting wrong exception types**
- **Found during:** Task T1 (AuthApiTest) — 404 test returned original `ModelNotFoundException` message
- **Issue:** Laravel 11's `Handler::render()` calls `prepareException()` BEFORE `renderViaCallbacks()`. `prepareException()` converts `ModelNotFoundException → NotFoundHttpException` and `AuthorizationException → AccessDeniedHttpException`. The custom handlers registered for `ModelNotFoundException` and `AuthorizationException` therefore never fired.
- **Fix:** Added handlers for `NotFoundHttpException` and `AccessDeniedHttpException` alongside existing handlers
- **Files modified:** `bootstrap/app.php`
- **Commit:** 7e9e427

**6. [Rule 1 - Bug] `TotalByCategory` resolver SQL table-alias conflict**
- **Found during:** Task T3 (GraphQLApiTest) — totalByCategory query returned 500
- **Issue:** Resolver used `Transaction::query()->from('transactions as t')` with alias `t`, but `SoftDeletes` global scope adds `WHERE "transactions"."deleted_at" IS NULL` using the real table name. SQLite cannot resolve `transactions.deleted_at` when table is aliased as `t` in `FROM "transactions" AS "t"`.
- **Fix:** Rewrote resolver using `Transaction::withoutGlobalScopes()` + explicit fully-qualified column names + `whereNull('transactions.deleted_at')` for manual soft-delete handling. Renamed `count` alias to `cnt` to avoid SQL reserved word.
- **Files modified:** `app/GraphQL/Queries/TotalByCategory.php`
- **Commit:** 7e9e427

### Design Decisions

1. **403 vs 404 for cross-user access:** `HasUserScoping` global scope filters records by `auth()->id()` at query time. Route model binding uses `findOrFail()` which applies global scopes. When userB accesses userA's resource, the record is not found → `ModelNotFoundException` → 404 (not 403). Tests reflect actual behavior.

2. **Logout test strategy:** In Laravel's test environment, after `withToken($token)->postJson(logout)` + `withToken($token)->getJson(resource)`, the auth guard may reuse cached user state, causing the revoked token to still pass. Test instead verifies `assertDatabaseMissing('personal_access_tokens')` to confirm token deletion.

3. **Factory setup before Sanctum::actingAs:** Because `HasUserScoping::bootHasUserScoping()` overrides `user_id` in the `creating` model event when `auth()->check()` is true, all factory records are created BEFORE calling `Sanctum::actingAs()` to ensure correct user_id assignment.

## Self-Check: PASSED

All 7 test files created and committed (7e9e427). All 148 tests pass.
