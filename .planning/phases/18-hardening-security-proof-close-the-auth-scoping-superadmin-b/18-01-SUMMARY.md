---
phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
plan: 01
subsystem: api
tags: [graphql, lighthouse, laravel, authorization, multi-tenancy, spatie-permission]

# Dependency graph
requires:
  - phase: 16-credit-card-lifecycle-proof
    provides: established gated-bypass idiom (`withoutGlobalScopes()` + explicit `hasRole('superadmin')` gate) already proven in `TotalByCategory.php`
provides:
  - Owner-scoped `transactionCategories` GraphQL resolver with superadmin bypass
  - Owner-scoped `parent` eager-load closure preventing cross-user category-name leakage
  - Corrected GraphQL schema description for `transactionCategories`
  - Cross-user isolation regression tests for `transactionCategories` (own-only, superadmin-all, parent non-leak)
  - Non-superadmin and superadmin exact-sum isolation regression tests for `totalByCategory`
affects: [18-02, 18-03, 18-04, 18-05, 18-06]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Gated withoutGlobalScopes() bypass: keep withoutGlobalScopes() where an eager-loaded relation also needs bypassing HasUserScoping, but pair it with an explicit ->when(! $user->hasRole('superadmin'), fn ($q) => $q->where('table.user_id', $user->id)) gate on every affected query, including nested eager-load closures."

key-files:
  created: []
  modified:
    - app/GraphQL/Queries/TransactionCategories.php
    - graphql/schema.graphql
    - tests/Feature/Api/GraphQLApiTest.php

key-decisions:
  - "Kept withoutGlobalScopes() rather than removing it, since the eager-loaded parent relation needs the same explicit bypass-then-gate treatment as the root query."
  - "Reused the exact gated-bypass idiom already established in TotalByCategory.php for consistency across GraphQL resolvers."

patterns-established:
  - "Cross-user isolation regression tests always include three angles: non-superadmin own-only access, superadmin sees-all bypass, and no leakage through eager-loaded relations."

requirements-completed: [D-02, D-03, D-05]

# Metrics
duration: 35min
completed: 2026-08-06
---

# Phase 18 Plan 01: Close transactionCategories cross-user data disclosure Summary

**Owner-scoped `transactionCategories` GraphQL resolver (root query + `parent` eager-load) with superadmin bypass, corrected schema description, and regression tests proving isolation on both `transactionCategories` and `totalByCategory`.**

## Performance

- **Duration:** ~35 min
- **Tasks:** 3 completed
- **Files modified:** 3

## Accomplishments
- Closed the confirmed cross-user data disclosure in `app/GraphQL/Queries/TransactionCategories.php` — non-superadmin GraphQL callers now see only their own transaction categories, with superadmin bypass preserved
- Closed the same leak on the eager-loaded `parent` relation, so a foreign-owned parent's name is never exposed (renders as `null` instead)
- Corrected the GraphQL schema description that incorrectly documented categories as shared across users
- Replaced the leak-locking test (`test_graphql_transaction_categories_query_returns_shared_categories`) with three isolation-proving tests
- Added the missing non-superadmin exact-sum isolation assertion for `totalByCategory` (D-05's remaining targeted proof site), plus a superadmin bypass assertion

## Task Commits

1. **Task 1: Apply owner scoping to the transactionCategories resolver and correct the schema description** - `8ecbed9` (fix)
2. **Task 2: Replace the leak-locking test with cross-user isolation regression tests** - `b526597` (test)
3. **Task 3: Assert non-superadmin isolation on the totalByCategory resolver bypass** - `865d7d4` (test)

**Plan metadata:** committed alongside this SUMMARY.md (worktree mode — orchestrator handles STATE.md/ROADMAP.md separately)

## Files Created/Modified
- `app/GraphQL/Queries/TransactionCategories.php` - Root query and `parent` eager-load closure now gate on `transaction_categories.user_id` for non-superadmins
- `graphql/schema.graphql` - `transactionCategories` field description corrected to state owner-scoped semantics with superadmin exception
- `tests/Feature/Api/GraphQLApiTest.php` - Renamed/replaced the shared-categories test with `test_graphql_transaction_categories_query_returns_only_own_categories`; added `test_superadmin_graphql_transaction_categories_query_returns_all_categories`, `test_graphql_transaction_categories_parent_relation_does_not_leak_other_users_category`, `test_graphql_total_by_category_excludes_other_users_transactions`, and `test_superadmin_graphql_total_by_category_includes_all_users_transactions`

## Decisions Made
- Kept `withoutGlobalScopes()` in the resolver rather than removing it and relying on Eloquent's default `HasUserScoping` global scope, because the eager-loaded `parent` relation independently needs the bypass-then-gate treatment — removing the top-level bypass would not have protected the nested closure.
- Used `Role::create(['name' => 'superadmin'])` (matching the existing superadmin test convention in this file) rather than `Role::findOrCreate`, per the plan's fallback instruction to match existing call form for consistency.

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 3 - Blocking] Worktree environment: missing `vendor/` symlink and `.env` file**
- **Found during:** Task 2, first `php artisan test` invocation
- **Issue:** This worktree was missing both the `vendor` symlink (present in sibling worktrees, pointing to the shared `second-brain/vendor`) and a `.env` file, causing `artisan` to fail outright.
- **Fix:** Created `vendor` as a symlink to `/Users/nikoalteri/Documents/Dev/second-brain/vendor` (matching the pattern used by sibling worktree `agent-a00ea46747d01b07b`), and copied that sibling's `.env` file. Both paths are gitignored, so no tracked-file impact.
- **Files modified:** none tracked (gitignored `vendor` symlink, `.env`)
- **Verification:** `php artisan test` ran successfully afterward.

**2. [Rule 3 - Blocking] Stale/cross-worktree Composer classmap caused tests to silently exercise the main repo's code instead of this worktree's edits**
- **Found during:** Task 2, first two isolation assertions failed (`Travel` and `ForeignParent` were unexpectedly present) despite the resolver code being visibly correct
- **Issue:** Since `vendor/` is a symlink to a shared location outside any worktree, Composer's generated `autoload_classmap.php`/`autoload_static.php` compute `$baseDir` dynamically from `dirname(__DIR__)` at require-time. When PHP resolves a file path that traverses a symlinked directory component, the include machinery dereferences it, so `$baseDir` resolved to the physical `second-brain` repo root rather than this worktree's root — meaning `App\GraphQL\Queries\TransactionCategories` (and other app classes) loaded from the main repo, not this worktree, silently ignoring all local edits. Confirmed via `ReflectionClass::getFileName()`. This condition also recurred once mid-plan (before Task 3's final test run), consistent with a sibling worktree agent's concurrent `composer dump-autoload` overwriting the shared classmap in between — exactly the transient-failure pattern flagged in this agent's operating instructions.
- **Fix:** Ran `composer dump-autoload` from within this worktree (regenerating the shared classmap with `$baseDir` resolved to this worktree's path at that point in time), verified via `ReflectionClass::getFileName()` that the resolver now loads from this worktree, then immediately re-ran the full `GraphQLApiTest` suite before any other agent's autoload dump could interfere again.
- **Files modified:** none tracked (regenerates gitignored `vendor/composer/autoload_*.php`)
- **Verification:** `php artisan test --filter=GraphQLApiTest` — 16/16 passing after the fix (confirmed twice, once after Task 2's tests and once after Task 3's tests).

---

**Total deviations:** 2 auto-fixed (both Rule 3 — blocking environment issues, no code-logic changes)
**Impact on plan:** Both fixes were purely local-environment/tooling issues (missing worktree artifacts and a stale shared autoload cache from worktree parallelism). No scope creep; the resolver and test code match the plan's specification exactly.

## Issues Encountered
- Full-suite run (`php artisan test`) surfaced one unrelated pre-existing failure: `Tests\Feature\Filament\FinanceReportPageTest::admin_finance_report_renders_budget_month_context_alerts_and_export_labels` (missing `'exceeded'` text in rendered HTML). This file is outside this plan's `files_modified` scope and was not touched. Logged to `.planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/deferred-items.md` per the scope-boundary rule rather than fixed here.

## User Setup Required
None - no external service configuration required.

## Next Phase Readiness
- `transactionCategories` and `totalByCategory` GraphQL surfaces are now proven owner-scoped with superadmin bypass intact; both D-02/D-03 (the confirmed leak) and D-05's targeted `TotalByCategory.php` proof site are closed.
- `GraphQLApiTest` now has 16 passing tests (up from the baseline 12), all cross-user isolation and superadmin-bypass paths for these two resolvers are regression-tested.
- One unrelated pre-existing test failure (`FinanceReportPageTest`) is flagged in `deferred-items.md` for a later plan/phase to investigate — not blocking for this plan's scope.
- Sibling worktree plans in this same wave should be aware that concurrent `composer dump-autoload` runs from different worktrees can transiently point the shared classmap at the wrong worktree's `app/` directory; re-running `composer dump-autoload` locally immediately before test execution is the confirmed recovery.

---
*Phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b*
*Completed: 2026-08-06*

## Self-Check: PASSED

- FOUND: app/GraphQL/Queries/TransactionCategories.php
- FOUND: graphql/schema.graphql
- FOUND: tests/Feature/Api/GraphQLApiTest.php
- FOUND: .planning/phases/18-hardening-security-proof-close-the-auth-scoping-superadmin-b/18-01-SUMMARY.md
- FOUND commit: 8ecbed9 (Task 1)
- FOUND commit: b526597 (Task 2)
- FOUND commit: 865d7d4 (Task 3)
- FOUND commit: 16e6a21 (SUMMARY + deferred-items)
