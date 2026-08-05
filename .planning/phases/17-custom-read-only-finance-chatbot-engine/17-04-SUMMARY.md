---
phase: 17-custom-read-only-finance-chatbot-engine
plan: 04
subsystem: api
tags: [laravel, php, chatbot, http, form-request, sanctum, tdd]

# Dependency graph
requires:
  - phase: 17-02
    provides: "IntentRouter, ChatIntent contract, UnsupportedIntentException"
  - phase: 17-03
    provides: "AccountBalancesIntent, UpcomingPaymentsIntent, MonthlySpendingIntent handlers"
provides:
  - "POST /api/v1/chatbot/ask — the live HTTP trust boundary for the whole chatbot engine"
  - "AskChatbotRequest — intent allow-list + params.days/params.month validation, no free-text catch-all"
  - "ChatbotController — thin delegator to IntentRouter, stateless, no persistence"
  - "IntentRouter singleton binding in AppServiceProvider, assembled from the three intent handlers"
  - "Global UnsupportedIntentException renderer in bootstrap/app.php (defense in depth)"
affects: [17-05-frontend-widget, 17-06-frontend-integration]

# Tech tracking
tech-stack:
  added: []
  patterns:
    - "Chatbot endpoint lives in the existing auth:sanctum + throttle:api-read read group — no new middleware group or rate limiter for a read-only feature"
    - "FormRequest allow-list (Rule::in) + router allow-list = two-layer reject-by-default for out-of-scope intents"

key-files:
  created:
    - tests/Feature/Api/ChatbotApiTest.php
    - app/Http/Requests/Api/AskChatbotRequest.php
    - app/Http/Controllers/Api/V1/ChatbotController.php
  modified:
    - app/Providers/AppServiceProvider.php
    - routes/api.php
    - bootstrap/app.php

key-decisions:
  - "params.days and params.month are the ONLY accepted free-text-adjacent params in v1 — no generic params.query/text catch-all, matching D-03's guided-flow-only free text boundary"
  - "ChatbotController has no try/catch; UnsupportedIntentException is rendered globally in bootstrap/app.php alongside AskChatbotRequest's Rule::in, so both layers independently enforce the same allow-list"

patterns-established:
  - "Read-only feature endpoints join the existing throttle:api-read group rather than spinning up a dedicated limiter"

requirements-completed: [D-01, D-03, D-04, D-07.1, D-07.2, D-07.3]

# Metrics
duration: 25min
completed: 2026-08-06
---

# Phase 17 Plan 04: HTTP Layer — Chatbot Endpoint Summary

**Live `POST /api/v1/chatbot/ask` endpoint behind `auth:sanctum` + `throttle:api-read`, validating the intent allow-list and the only two accepted free-text params (`days`, `month`) before the IntentRouter (assembled via a new container binding) ever runs a query.**

## Performance

- **Duration:** ~25 min
- **Tasks:** 3 completed
- **Files modified:** 6 (3 created, 3 modified)

## Accomplishments
- `ChatbotApiTest` encodes the full endpoint contract from `17-VALIDATION.md`: auth boundary, out-of-scope rejection, scoped account balances (including cross-user leak prevention), upcoming-payments parity with the dashboard endpoint, monthly-spending totals, malformed free-text param rejection, and the read-throttle middleware stack
- `AskChatbotRequest` validates `intent` against `IntentRouter::SUPPORTED_INTENTS` and only allows `params.days` (integer 1-30) and `params.month` (`Y-m` format) — no free-text catch-all field exists anywhere in the request
- `ChatbotController` is a stateless, single-method delegator to `IntentRouter::route()` — no session writes, no persistence, no try/catch
- `POST chatbot/ask` registered inside the existing "Read endpoints — 100 req/min" group; `IntentRouter` is bound as a container singleton assembled from the three real intent handlers; `UnsupportedIntentException` is rendered globally as a 422 JSON response
- Full TDD RED → GREEN cycle: 8 failing tests (route/class not found) → all 8 green after Tasks 2-3
- Full suite green apart from 2 pre-existing unrelated failures already logged in `deferred-items.md` by plans 17-01/17-02/17-03

## Task Commits

1. **Task 1: Write the failing ChatbotApiTest feature suite** - `7839012` (test — RED)
2. **Task 2: AskChatbotRequest and ChatbotController** - `ca1bf5d` (feat)
3. **Task 3: Wire route, container binding, and exception renderer** - `ce2fa7d` (feat — GREEN, includes a test-side type-mismatch fix, see Deviations)

**Plan metadata:** committed together with this SUMMARY.md (see final commit)

## Files Created/Modified
- `tests/Feature/Api/ChatbotApiTest.php` - 8 feature tests covering the endpoint's full contract (auth, scope, out-of-scope, param validation, throttle middleware, dashboard parity)
- `app/Http/Requests/Api/AskChatbotRequest.php` - `authorize(): true` (route-level auth), `intent` allow-list via `Rule::in`, `params.days`/`params.month` validation, fixed out-of-scope message
- `app/Http/Controllers/Api/V1/ChatbotController.php` - `ask()` action, constructor-injected `IntentRouter`, no try/catch
- `app/Providers/AppServiceProvider.php` - `register()` now binds `IntentRouter::class` as a singleton, resolving `AccountBalancesIntent`, `UpcomingPaymentsIntent`, `MonthlySpendingIntent` via `$app->make()`
- `routes/api.php` - `ChatbotController` import (alphabetical) + `POST chatbot/ask` inside the existing `auth:sanctum` + `throttle:api-read` group
- `bootstrap/app.php` - `UnsupportedIntentException` import + global renderer returning `{"message": ...}` with 422, appended as the last `withExceptions` entry

## Decisions Made
- Followed the plan's exact code blocks verbatim for `AskChatbotRequest`, `ChatbotController`, the `AppServiceProvider::register()` binding, and the `bootstrap/app.php` renderer — no application-code deviations
- Kept the two-layer allow-list (FormRequest `Rule::in` + router's own `SUPPORTED_INTENTS` check) exactly as specified — both are required per the plan, not redundant

## Deviations from Plan

### Auto-fixed Issues

**1. [Rule 1 - Bug] Fixed a float/int type mismatch in the parity and totals assertions**
- **Found during:** Task 3 (full suite run after wiring the route)
- **Issue:** `test_chatbot_upcoming_payments_matches_dashboard` and `test_chatbot_monthly_spending_intent_returns_correct_totals` (both written verbatim from the plan's Task 1 spec) compared JSON-decoded response values with `assertSame`/`assertJsonPath`. PHP's default `json_encode` drops the trailing `.0` from whole-number floats (`json_encode(round(250.0, 2))` → `"250"`, decoded back as `int(250)`), while the dashboard-side comparison array explicitly re-cast every value to `(float)` after decoding. With the loan/credit-card fixture amounts being whole numbers (250, 180) and the monthly totals being whole numbers (2000, 500, 1500), this asymmetry caused `assertSame(250.0, 250)` and `assertJsonPath('data.items.0.value', 2000.0)` to fail even though the application logic was correct.
- **Fix:** In `test_chatbot_upcoming_payments_matches_dashboard`, cast the chat-side map to `round((float) $item['value'], 2)` matching the dashboard-side cast. In `test_chatbot_monthly_spending_intent_returns_correct_totals`, replaced the float-literal `assertJsonPath` value assertions with explicit `round((float) $response->json(...), 2)` comparisons via `assertSame`.
- **Files modified:** `tests/Feature/Api/ChatbotApiTest.php`
- **Verification:** `php artisan test --filter=ChatbotApiTest` — all 8 tests green; `php artisan test` — full suite green apart from 2 pre-existing unrelated failures
- **Committed in:** `ce2fa7d` (Task 3 commit)

---

**Total deviations:** 1 auto-fixed (1 bug)
**Impact on plan:** Test-only fix; no production code changed beyond what the plan specified. No scope creep — the application's monetary values were already correct, only the test's type-strict comparison needed hardening against PHP's JSON float serialization quirk.

## Issues Encountered
- Fresh worktree had no `vendor/` or `.env` (both gitignored, consistent with plans 17-01/17-02/17-03). Copied both from the sibling main checkout (`cp -a`) to run `php artisan test`. Nothing tracked was touched; `git status --short` is clean.
- `php artisan route:list --path=chatbot --json` resolves middleware aliases to fully-qualified class names (`Illuminate\Auth\Middleware\Authenticate:sanctum`, `Illuminate\Routing\Middleware\ThrottleRequests:api-read`) rather than the literal alias strings `auth:sanctum`/`throttle:api-read` the plan's acceptance-criteria grep expects — a pre-existing Laravel version behavior, not a defect introduced here. The functionally equivalent and more precise check, `test_chatbot_ask_uses_api_read_throttle` (one of the 8 mandated `ChatbotApiTest` tests, using `Route::gatherMiddleware()`), correctly asserts both alias strings and passes.
- Pre-existing, unrelated to this plan's files: `Tests\Feature\ExampleTest` fails because `public/build/manifest.json` (gitignored Vite build output) doesn't exist in this fresh worktree. `Tests\Feature\Filament\FinanceReportPageTest` fails on `assertSee('exceeded')`. Both already logged in `.planning/phases/17-custom-read-only-finance-chatbot-engine/deferred-items.md` by plans 17-02/17-03; out of scope for this plan's files.

## User Setup Required

None - no external service configuration required.

## Next Phase Readiness
- The chatbot engine is fully live end-to-end: `POST /api/v1/chatbot/ask` answers all three D-07 intents for the authenticated user only, rejects out-of-scope intents and malformed params before any query runs, and sits behind the same auth + rate-limit guards as every other read endpoint
- Plans 17-05/17-06 (frontend widget) can build directly against the locked wire format (`{ "intent": ..., "params": {...} }` request, `{ "data": { intent, headline, highlight, items, empty_message } }` response) with no further backend exploration needed
- No blockers

---
*Phase: 17-custom-read-only-finance-chatbot-engine*
*Completed: 2026-08-06*
