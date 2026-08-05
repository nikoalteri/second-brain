---
phase: 17-custom-read-only-finance-chatbot-engine
plan: 02
subsystem: api
tags: [php, laravel, chatbot, intent-router, tdd]

# Dependency graph
requires: []
provides:
  - "ChatIntent interface — the answer-payload contract every intent handler implements"
  - "UnsupportedIntentException — fixed out-of-scope user-facing message + attacker-supplied key accessor"
  - "IntentRouter — stateless allow-list dispatcher for the three D-07 intent keys"
affects: [17-03-intent-handlers, 17-04-http-layer]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Intent contract layer under app/Services/Chatbot with dedicated Contracts/ and Exceptions/ subnamespaces"
    - "Two-layer reject-by-default dispatch: allow-list constant check before handler-map lookup"

key-files:
  created:
    - app/Services/Chatbot/Contracts/ChatIntent.php
    - app/Services/Chatbot/Exceptions/UnsupportedIntentException.php
    - app/Services/Chatbot/IntentRouter.php
    - tests/Unit/Services/Chatbot/IntentRouterTest.php
  modified: []

key-decisions:
  - "Router checks SUPPORTED_INTENTS allow-list AND intents.get()->instanceof, not just one — hard D-01 scope boundary independent of what handlers happen to be registered"
  - "UnsupportedIntentException never concatenates the attacker-supplied key into getMessage(); key is only exposed via intentKey() accessor (T-17-06 mitigation)"

patterns-established:
  - "Chatbot intent handlers implement App\\Services\\Chatbot\\Contracts\\ChatIntent and return the locked five-key answer payload (intent, headline, highlight, items, empty_message)"

requirements-completed: [D-01, D-02, D-04, D-08]

# Metrics
duration: 15min
completed: 2026-08-06
---

# Phase 17 Plan 02: Chatbot Intent Contract and Stateless Router Summary

**Stateless IntentRouter with a hard three-key allow-list (account_balances, upcoming_payments, monthly_spending), the ChatIntent handler contract, and UnsupportedIntentException carrying the fixed UI-SPEC out-of-scope message.**

## Performance

- **Duration:** ~15 min
- **Tasks:** 3 completed
- **Files modified:** 4 (3 created production, 1 created test)

## Accomplishments
- `ChatIntent` interface locks the v1 answer-payload shape (`intent`, `headline`, `highlight`, `items`, `empty_message`) that plan 17-03's three handlers must implement
- `UnsupportedIntentException` carries the exact UI-SPEC out-of-scope copy and exposes the untrusted intent key only via `intentKey()`, never in the message string
- `IntentRouter` dispatches to a handler map keyed by `ChatIntent::key()`, rejects anything outside `SUPPORTED_INTENTS` before even checking the handler map, and holds no state between calls (D-04)
- Full TDD cycle for the router: RED (4 failing tests, `IntentRouter` class missing) → GREEN (4 passing tests, full suite green apart from pre-existing unrelated failure)

## Task Commits

1. **Task 1: Define the ChatIntent contract and UnsupportedIntentException** - `10354c6` (feat)
2. **Task 2: Write failing unit test for IntentRouter dispatch and rejection** - `3e42465` (test — RED)
3. **Task 3: Implement the stateless IntentRouter** - `ad9f479` (feat — GREEN)

**Plan metadata:** committed together with this SUMMARY.md (see final commit)

## Files Created/Modified
- `app/Services/Chatbot/Contracts/ChatIntent.php` - Interface: `key(): string` and `handle(User, array): array` with the locked five-key return-type docblock
- `app/Services/Chatbot/Exceptions/UnsupportedIntentException.php` - Domain exception, fixed UI-SPEC message, `intentKey()` accessor for the rejected key
- `app/Services/Chatbot/IntentRouter.php` - `SUPPORTED_INTENTS` allow-list constant, keyBy-collection handler map, `route(User, string, array): array` with double reject-by-default guard, no NLU/pattern matching
- `tests/Unit/Services/Chatbot/IntentRouterTest.php` - 4 pure unit tests (no `RefreshDatabase`) covering dispatch, param pass-through, unknown-key rejection, and exact exception message/key

## Decisions Made
- Kept the two rejection checks in `IntentRouter::route` separate (allow-list constant, then handler-map `instanceof` check) exactly as the plan specified — this is the D-01 scope boundary and remains correct even if a handler for an out-of-scope key were ever mistakenly registered
- No `app/Exceptions/` directory created; `UnsupportedIntentException` lives inside `App\Services\Chatbot\Exceptions` per RESEARCH.md's recommended structure

## Deviations from Plan

None - plan executed exactly as written. Files match the plan's exact code blocks verbatim.

## Issues Encountered
- The worktree had no `vendor/` directory or `.env` file (fresh git worktree, `vendor` and `.env` are gitignored). Symlinked `vendor` to the sibling main-repo checkout (identical `composer.lock`) and copied `.env` from the main repo to run `php artisan test` and `php -l`. Neither is a tracked change — `git status --short` confirms no diff from this. Regenerated the optimized autoloader (`composer dump-autoload -o`) once, which was necessary for the newly-created `IntentRouter` class to be discovered by PHPUnit (classmap-based autoload had to include it before it existed on disk).
- Pre-existing, unrelated to this plan's files: `tests/Unit/SecurityChecklistTest.php` uses `#[Test]` without importing `PHPUnit\Framework\Attributes\Test`, so PHPUnit reports "No tests found" for it (the plan's Task 1 verify command references this test). Also pre-existing: `tests/Feature/Filament/FinanceReportPageTest.php` fails on `assertSee('exceeded')` in the full suite run. Both logged to `.planning/phases/17-custom-read-only-finance-chatbot-engine/deferred-items.md` per the scope-boundary rule (out of scope for this plan's files).

## Next Phase Readiness
- The `ChatIntent` contract and answer-payload shape are locked; plan 17-03 can implement the three intent handlers (`account_balances`, `upcoming_payments`, `monthly_spending`) against them without further exploration
- `IntentRouter` is ready to be constructed with the three real handlers once 17-03 lands; plan 17-04 can wire it into the HTTP layer and register `UnsupportedIntentException`'s renderer in `bootstrap/app.php`
- No blockers

---
*Phase: 17-custom-read-only-finance-chatbot-engine*
*Completed: 2026-08-06*

## Self-Check: PASSED

All 4 created files verified present on disk; all 3 task commits (`10354c6`, `3e42465`, `ad9f479`) verified present in git log.
