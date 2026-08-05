---
phase: 17-custom-read-only-finance-chatbot-engine
plan: 01
subsystem: api
tags: [laravel, php, service-extraction, tdd, dashboard]

# Dependency graph
requires: []
provides:
  - "App\\Services\\UpcomingPaymentsService::forUser(User, int): array as the single source of upcoming-payments aggregation"
  - "Thinned DashboardController::upcomingPayments() delegating to the service"
affects: [17-03-custom-read-only-finance-chatbot-engine]

# Tech tracking
tech-stack:
  added: []
  patterns: ["Flat App\\Services class extraction from controller-inline aggregation logic, mirroring FinanceReportService/SubscriptionService style"]

key-files:
  created:
    - app/Services/UpcomingPaymentsService.php
    - tests/Unit/Services/UpcomingPaymentsServiceTest.php
  modified:
    - app/Http/Controllers/Api/V1/DashboardController.php

key-decisions:
  - "Extracted the ~85-line inline aggregation verbatim into UpcomingPaymentsService::forUser(User, int): array, preserving all three per-user ownership guards unchanged"
  - "Controller keeps `days` validation; service receives a typed int, closing the tampering vector called out in the threat model"

patterns-established:
  - "Read-only aggregation services return plain arrays (not JsonResponse), leaving response shaping to the controller"

requirements-completed: [D-07.2]

# Metrics
duration: ~35min
completed: 2026-08-05
---

# Phase 17 Plan 01: Extract UpcomingPaymentsService Summary

**Extracted the dashboard's inline upcoming-payments aggregation (loans, credit-card payments, subscriptions) into `App\Services\UpcomingPaymentsService::forUser()`, giving the Phase 17 chatbot engine a single reusable, ownership-scoped source of the same data the dashboard already surfaces.**

## Performance

- **Duration:** ~35 min
- **Started:** 2026-08-05T22:15:00Z
- **Completed:** 2026-08-05T22:54:04Z
- **Tasks:** 3
- **Files modified:** 3 (2 created, 1 modified)

## Accomplishments
- Moved the loan/credit-card/subscription upcoming-payments merge-and-sort logic out of `DashboardController::upcomingPayments()` into `App\Services\UpcomingPaymentsService`
- Preserved all three `->when(! $user->hasRole('superadmin'), ...)` ownership guards verbatim (loan via `whereHas`, credit card via `whereHas`, subscription via `user_id`)
- `DashboardController::upcomingPayments()` is now a 10-line validate-and-delegate action
- 4 new unit tests cover merge/sort, per-source filtering, cross-user exclusion, and paid-status exclusion
- `DashboardApiTest` (existing regression contract) passes unchanged — response shape is byte-identical

## Task Commits

Each task was committed atomically (TDD RED/GREEN/refactor cycle):

1. **Task 1: Write failing unit test for UpcomingPaymentsService** - `a0b10f9` (test)
2. **Task 2: Create UpcomingPaymentsService with the extracted aggregation** - `c015d37` (feat)
3. **Task 3: Delegate DashboardController::upcomingPayments to the service** - `604f925` (refactor)

Supporting docs commits (deferred-items tracking, not part of the TDD gate sequence):
- `36bdf5c` (docs) — logged pre-existing unrelated FinanceReportPageTest failure
- `c399b9c` (docs) — logged worktree-only Vite manifest gap

**Plan metadata:** this commit (docs: complete plan)

## Files Created/Modified
- `app/Services/UpcomingPaymentsService.php` - New service; `forUser(User $user, int $days = 3): array` merges pending loan payments, credit-card payments, and active subscriptions due within the window, sorted by due date, with per-user ownership guards
- `tests/Unit/Services/UpcomingPaymentsServiceTest.php` - 4 unit tests: single-source loan payment shape, three-source merge/sort order, cross-user exclusion, paid-status exclusion
- `app/Http/Controllers/Api/V1/DashboardController.php` - `upcomingPayments()` now validates `days` and delegates to `$this->upcomingPaymentsService->forUser(...)`; constructor swapped `SubscriptionService` for `UpcomingPaymentsService`; removed now-unused `LoanPayment`/`CreditCardPayment`/`Subscription`/`SubscriptionService` imports

## Decisions Made
- Kept validation (`days` between 1 and 30) in the controller per the plan, so the service's `int $days` signature can never receive an unvalidated/string value (mitigates T-17-02 from the threat model)
- Did not touch `charts()` or its private helpers — out of scope per the plan's explicit instruction that plan 17-04 reuses the already-extracted `FinanceReportService::getTable()` instead

## Deviations from Plan

None in application code - plan executed exactly as written (verbatim extraction, exact controller rewrite as specified).

### Auto-fixed Issues (environment only, Rule 3 - blocking)

**1. [Rule 3 - Blocking] Worktree had no local `vendor/`, `.env`, or built frontend assets**
- **Found during:** Task 1 (running the RED test for the first time)
- **Issue:** This git worktree was created without `composer install` output or a `.env` file, so `php artisan test` failed immediately with a missing autoloader, and later a missing `APP_KEY`.
- **Fix:** Initially symlinked `vendor/` to the main checkout, then switched to a local `cp -a` copy after discovering a concurrent worktree agent's `composer dump-autoload` had overwritten the shared `vendor/composer/autoload_real.php` with its own worktree's `$baseDir`, breaking autoloading for this agent. Copied `.env` from the main checkout to supply `APP_KEY` and other config for the test run. `vendor/` and `.env` are both gitignored, so nothing was committed.
- **Files modified:** None tracked (vendor/, .env are gitignored, local-only)
- **Verification:** `php artisan test --filter=UpcomingPaymentsServiceTest` and `php artisan test --filter=DashboardApiTest` both green after the fix
- **Committed in:** N/A (gitignored, no commit)

---

**Total deviations:** 1 auto-fixed (1 blocking, environment-only, no application code touched)
**Impact on plan:** No scope creep — purely local worktree tooling fixes required to execute tests at all. Application code matches the plan exactly.

## Issues Encountered
- Full `php artisan test` run surfaces two pre-existing, unrelated failures documented in `.planning/phases/17-custom-read-only-finance-chatbot-engine/deferred-items.md`:
  1. `Tests\Feature\Filament\FinanceReportPageTest::admin_finance_report_renders_budget_month_context_alerts_and_export_labels` — confirmed pre-existing via `git stash` before any 17-01 changes; unrelated to `DashboardController`/`UpcomingPaymentsService`.
  2. `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response` — fails only because `public/build/manifest.json` (gitignored, Vite build output) does not exist in this fresh worktree; not an application bug.
  Both are out of scope per the executor's scope-boundary rule and were not modified.
- The plan's overall `<verification>` block expects `grep -rn "LoanPayment::query()" app/` to return exactly one hit. It actually returns two: the new hit in `UpcomingPaymentsService.php` (as expected) and one pre-existing, unrelated hit in `app/Services/LoanPaymentPostingService.php` (loan-payment posting workflow, not aggregation). The plan's per-task acceptance criteria (`DashboardController.php` has zero `LoanPayment::query()`/`CreditCardPayment::query()` occurrences) are satisfied; only the broader whole-app duplication check assumption was slightly inaccurate due to this pre-existing file.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- `App\Services\UpcomingPaymentsService::forUser(User, int): array` is ready for plan 17-03 (chatbot intent) to call directly, satisfying D-07.2 ("reuse the same data the dashboard's upcoming-payments widget already surfaces")
- No blockers for downstream Phase 17 plans

---
*Phase: 17-custom-read-only-finance-chatbot-engine*
*Completed: 2026-08-05*

## Self-Check: PASSED

- FOUND: app/Services/UpcomingPaymentsService.php
- FOUND: tests/Unit/Services/UpcomingPaymentsServiceTest.php
- FOUND: .planning/phases/17-custom-read-only-finance-chatbot-engine/17-01-SUMMARY.md
- FOUND commits: a0b10f9, c015d37, 604f925, 36bdf5c, c399b9c, 58bf4c4
