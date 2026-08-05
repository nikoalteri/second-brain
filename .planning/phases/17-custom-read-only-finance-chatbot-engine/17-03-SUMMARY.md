---
phase: 17-custom-read-only-finance-chatbot-engine
plan: 03
subsystem: api
tags: [laravel, php, chatbot, intent-handlers, tdd]

# Dependency graph
requires:
  - "App\\Services\\UpcomingPaymentsService::forUser(User, int): array (plan 17-01)"
  - "App\\Services\\Chatbot\\Contracts\\ChatIntent (plan 17-02)"
provides:
  - "App\\Services\\Chatbot\\Intents\\AccountBalancesIntent — account_balances answer built from Account model"
  - "App\\Services\\Chatbot\\Intents\\UpcomingPaymentsIntent — upcoming_payments answer built from UpcomingPaymentsService"
  - "App\\Services\\Chatbot\\Intents\\MonthlySpendingIntent — monthly_spending answer built from FinanceReportService::getTable"
  - "App\\Services\\Chatbot\\Concerns\\ResolvesUserCurrency — shared currency resolution trait"
affects: [17-04-http-layer]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Chatbot intent handlers as thin adapters over already-tested data paths (Account model, UpcomingPaymentsService, FinanceReportService) — never re-derive figures"
    - "Shared ResolvesUserCurrency trait avoids duplicating the active-account currency lookup across intent handlers"

key-files:
  created:
    - app/Services/Chatbot/Concerns/ResolvesUserCurrency.php
    - app/Services/Chatbot/Intents/AccountBalancesIntent.php
    - app/Services/Chatbot/Intents/UpcomingPaymentsIntent.php
    - app/Services/Chatbot/Intents/MonthlySpendingIntent.php
    - tests/Unit/Services/Chatbot/ChatbotAccountBalancesIntentTest.php
    - tests/Unit/Services/Chatbot/ChatbotUpcomingPaymentsIntentTest.php
    - tests/Unit/Services/Chatbot/ChatbotMonthlySpendingIntentTest.php
  modified: []

key-decisions:
  - "AccountBalancesIntent and UpcomingPaymentsIntent both scope with the same `when(! $user->hasRole('superadmin'), ...)` idiom as AccountController/DashboardController, on top of Account's existing HasUserScoping global scope"
  - "UpcomingPaymentsIntent contains zero direct model queries — 100% delegation to UpcomingPaymentsService::forUser, verified by a grep gate for LoanPayment::/CreditCardPayment::/Subscription:: usage"
  - "MonthlySpendingIntent indexes FinanceReportService::getTable() with month-1, never touching index 12 (the TOTALE row)"

requirements-completed: [D-01, D-07.1, D-07.2, D-07.3]

# Metrics
duration: ~35min
completed: 2026-08-06
---

# Phase 17 Plan 03: D-07 Intent Handlers Summary

**Three thin `ChatIntent` adapters (`AccountBalancesIntent`, `UpcomingPaymentsIntent`, `MonthlySpendingIntent`) plus a shared `ResolvesUserCurrency` trait, each delegating entirely to already-tested data paths (`Account` model, `UpcomingPaymentsService`, `FinanceReportService::getTable`) so the chatbot can never disagree with the dashboard or finance report it echoes.**

## Performance

- **Duration:** ~35 min
- **Tasks:** 3 completed
- **Files modified:** 7 (4 created production, 3 created test)

## Accomplishments

- `AccountBalancesIntent` returns the authenticated user's active accounts with a `Total` highlight, scoped by the same superadmin/ownership idiom as `AccountController::index`
- `UpcomingPaymentsIntent` maps `UpcomingPaymentsService::forUser()` rows into the answer contract with a `Total due` highlight, honoring the `days` param; contains zero direct model queries
- `MonthlySpendingIntent` reads the current (or requested) month row from `FinanceReportService::getTable()` as Earnings/Expenses/Net, never returning the `TOTALE` row
- `ResolvesUserCurrency` trait centralizes the "first active account's currency, fallback EUR" lookup used by all three handlers
- Full TDD RED/GREEN cycle for all three tasks: 12 new unit tests, all green
- Full suite run: 140 passed, 2 pre-existing unrelated failures (documented below), no regressions from this plan's files

## Task Commits

Each task followed the TDD RED/GREEN cycle with atomic commits:

1. **Task 1: AccountBalancesIntent + shared currency trait**
   - `231eba6` (test) — failing test for `AccountBalancesIntent`
   - `58b56d8` (feat) — `ResolvesUserCurrency` trait + `AccountBalancesIntent` implementation
2. **Task 2: UpcomingPaymentsIntent over the shared service**
   - `7d14fd0` (test) — failing test for `UpcomingPaymentsIntent`
   - `974bb87` (feat) — `UpcomingPaymentsIntent` implementation
3. **Task 3: MonthlySpendingIntent over FinanceReportService**
   - `9661e7b` (test) — failing test for `MonthlySpendingIntent`
   - `bf0e048` (feat) — `MonthlySpendingIntent` implementation

**Plan metadata:** this commit (docs: complete plan)

## Files Created/Modified

- `app/Services/Chatbot/Concerns/ResolvesUserCurrency.php` — Trait resolving the authenticated user's currency from their first active account (ordered by `id`), falling back to `'EUR'`
- `app/Services/Chatbot/Intents/AccountBalancesIntent.php` — `key() === 'account_balances'`; queries `Account` scoped by ownership + `is_active`, returns items sorted by name plus a `Total` highlight, or the empty-state message when no active accounts exist
- `app/Services/Chatbot/Intents/UpcomingPaymentsIntent.php` — `key() === 'upcoming_payments'`; constructor-injects `UpcomingPaymentsService`, maps its rows (loan/credit-card/subscription) into answer items with a `Total due` highlight and a `days`-aware headline/empty message
- `app/Services/Chatbot/Intents/MonthlySpendingIntent.php` — `key() === 'monthly_spending'`; constructor-injects `FinanceReportService`, parses an optional `Y-m` `month` param (defaulting to the current month), indexes `getTable()[$month->month - 1]`, returns Earnings/Expenses/Net items plus a Net highlight
- `tests/Unit/Services/Chatbot/ChatbotAccountBalancesIntentTest.php` — 4 tests: active balances + total, excludes inactive, excludes other users, empty-state message
- `tests/Unit/Services/Chatbot/ChatbotUpcomingPaymentsIntentTest.php` — 4 tests: maps service rows into items, totals the highlight across payment types, honors the `days` param, empty-state message
- `tests/Unit/Services/Chatbot/ChatbotMonthlySpendingIntentTest.php` — 4 tests: current-month default totals, explicit `month` param, never returns the TOTALE row, excludes other users' transactions

## Decisions Made

- Followed the plan's exact code blocks verbatim for the trait and all three handlers — no application-code deviations
- Test fixtures mirror the conventions already established in `UpcomingPaymentsServiceTest` (loan/credit-card/subscription setup) and `FinanceReportApiTest` (`TransactionType::firstOrCreate` for income/expense types) rather than inventing new patterns
- Used `$this->actingAs($user)` (not `Sanctum::actingAs`) per the plan's explicit instruction, since `HasUserScoping`'s global scope reads `auth()->check()` from the default guard

## Deviations from Plan

None — plan executed exactly as written. All three handler classes and the trait match the plan's code blocks verbatim; only the three test files (whose exact assertions were left to the executor per the plan's `<behavior>` specs) were newly authored.

### Environment-only setup (not a plan deviation)

This worktree had no local `vendor/` or `.env` (both gitignored). Copied both from the sibling main checkout (`cp -a`, not a symlink, to avoid the shared-`vendor/` autoload corruption documented in plan 17-01's summary) to run `php artisan test`. Nothing tracked was touched; `git status --short` is clean.

## Issues Encountered

Full `php artisan test` run surfaces the same two pre-existing, unrelated failures already logged in `.planning/phases/17-custom-read-only-finance-chatbot-engine/deferred-items.md` by plans 17-01/17-02:

1. `Tests\Feature\ExampleTest::test_the_application_returns_a_successful_response` — fails because `public/build/manifest.json` (gitignored Vite build output) doesn't exist in this fresh worktree; not an application bug.
2. `Tests\Feature\Filament\FinanceReportPageTest::admin_finance_report_renders_budget_month_context_alerts_and_export_labels` — pre-existing, unrelated to any Chatbot file.

Neither touches any file this plan created or modified; both are out of scope per the executor's scope-boundary rule.

## User Setup Required

None — no external service configuration required.

## Next Phase Readiness

- `IntentRouter` (plan 17-02) can now be constructed with all three real handlers: `AccountBalancesIntent`, `UpcomingPaymentsIntent`, `MonthlySpendingIntent`
- Plan 17-04 can wire the router into the HTTP layer (`AskChatbotRequest` + controller) without further intent-handler work
- No blockers

---
*Phase: 17-custom-read-only-finance-chatbot-engine*
*Completed: 2026-08-06*

## Self-Check: PASSED

- FOUND: app/Services/Chatbot/Concerns/ResolvesUserCurrency.php
- FOUND: app/Services/Chatbot/Intents/AccountBalancesIntent.php
- FOUND: app/Services/Chatbot/Intents/UpcomingPaymentsIntent.php
- FOUND: app/Services/Chatbot/Intents/MonthlySpendingIntent.php
- FOUND: tests/Unit/Services/Chatbot/ChatbotAccountBalancesIntentTest.php
- FOUND: tests/Unit/Services/Chatbot/ChatbotUpcomingPaymentsIntentTest.php
- FOUND: tests/Unit/Services/Chatbot/ChatbotMonthlySpendingIntentTest.php
- FOUND commits: 231eba6, 58b56d8, 7d14fd0, 974bb87, 9661e7b, bf0e048
