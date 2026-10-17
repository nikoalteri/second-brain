---
phase: 24
slug: cash-flow-forecast-safe-to-spend
status: validated
nyquist_compliant: true
wave_0_complete: true
created: 2026-10-17
---

# Phase 24 — Validation Strategy

> Reconstructed after the fact (state B of `validate-phase`) from the PLAN and SUMMARY files and the merged code (PR #72). The GSD skills were not loaded in the execution session, so the phase was executed by hand and this audit was done later, following the validate-phase workflow manually (no auditor sub-agent). The one gap found (the rendered card) was then closed by adding Vitest + Vue Test Utils and `tests/Frontend/CashFlowForecastCard.spec.js`.

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11 (backend); plain Node `.mjs` assertions (frontend) |
| **Config file** | `phpunit.xml`; `vitest.config.js` (Vitest + Vue Test Utils + jsdom) |
| **Quick run command** | `php -d memory_limit=-1 vendor/bin/phpunit tests/Unit/Services/Forecast tests/Feature/Api/CashFlowForecastApiTest.php` |
| **Full suite command** | `APP_KEY=base64:<synthetic> php -d memory_limit=-1 vendor/bin/phpunit` (656 tests green on the merged head) |
| **Frontend command** | `npm run test:frontend && node tests/Frontend/cash-flow-forecast.mjs && npm run build` |
| **Estimated runtime** | ~1 s quick, ~18 s full |

## Sampling Rate

- **After every task commit:** quick run command
- **After every plan wave:** full suite
- **Before verify-work:** full suite green and `npm run build` passing

## Per-Task Verification Map

| Task | Plan | Wave | Requirement | Behavior | Test Type | Automated Command | Status |
|------|------|------|-------------|----------|-----------|-------------------|--------|
| 24-01-1/2 | 01 | 1 | D-01 | Inclusive 30-day window (day 30 in, day 31 out) | unit | `phpunit tests/Unit/Services/Forecast` (`test_window_is_inclusive_of_day_thirty…`) | ✅ green |
| 24-01-2/3 | 01 | 1 | D-02 | Loan, card payment, subscription classification; posted / soft-deleted / paid-without-posting / overdue; card vs account renewals; owner scoping incl. superadmin | unit | `phpunit tests/Unit/Services/Forecast` (23 tests) | ✅ green |
| 24-01-4 | 01 | 1 | D-02 | Existing upcoming-payments and chatbot behaviour unchanged | unit/feature | `phpunit tests/Unit/Services/UpcomingPaymentsServiceTest.php tests/Feature/Api/DashboardApiTest.php` | ✅ green |
| 24-02-1 | 02 | 2 | D-03 | Goal-linked account excluded whole; archived goal not excluded; inactive and debt/credit_card accounts excluded | feature | `phpunit tests/Feature/Api/CashFlowForecastApiTest.php` | ✅ green |
| 24-02-2/3 | 02 | 2 | D-01, D-02 | Per-currency arithmetic, no grand total, negative result unclamped, never `complete`, coverage flags | feature | same | ✅ green |
| 24-02-3 | 02 | 2 | D-04 | Endpoint requires auth; other users' data never returned (superadmin personal view) | feature | same | ✅ green |
| 24-03-1/2 | 03 | 3 | D-04 | Per-currency rows, incomplete/empty-state logic, coverage copy | node | `node tests/Frontend/cash-flow-forecast.mjs` (view model only) | ✅ green |
| 24-03-3 | 03 | 3 | D-04 | Stale-session response discarded; failure isolated from other dashboard data | node | same | ✅ green |
| 24-03-1/2 | 03 | 3 | D-04 | Rendered card: one article per currency in its own currency, no combined total, negative remainder in warning colour, incomplete badge, overdue box, coverage copy and disclaimer, non-deducted item reason, empty state, isolated error with retry, loading state (7 tests; mutation-checked) | component | `npm run test:frontend` | ✅ green |
| 24-03-4 | 03 | 3 | D-04 | SPA builds | build | `npm run build` | ✅ green |

## Gap Analysis

| Requirement | Status | Note |
|-------------|--------|------|
| D-01 fixed 30-day window | COVERED | The "not user-configurable" part is untested but trivially true: the route takes no parameters |
| D-02 three commitment types | COVERED | |
| D-03 balance baseline | COVERED | Account types `debt`/`credit_card` excluded by review decision |
| D-04 Dashboard surface | COVERED | API, view model and the rendered card states are automated; theme/responsive/real-data checks stay manual |

## Wave 0 Requirements

Existing infrastructure covers all backend requirements. The frontend component runner (Vitest + Vue Test Utils + jsdom) was added during this audit; `vitest.config.js` reuses the `@` alias and `tests/Frontend/*.spec.js`. The older `.mjs` tests are unchanged.

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Status |
|----------|-------------|------------|--------|
| Card layout, EUR/USD rows separate, incomplete badge, warnings | D-04 | layout is visual | ✅ done 2026-10-17: desktop 1440px and mobile 500px, dark theme, seeded local data |
| Empty and error states in the real page | D-04 | covered by component tests; page-level look not viewed | ⬜ not checked |
| Light theme rendering | D-04 | visual | ⬜ not checked |
| Mobile width below 500px | D-04 | browser window minimum is 500px | ⬜ not checked |
| Real data on UAT (balances, overdue items, card renewals) | D-02, D-03 | depends on real records | ⬜ not checked |

## Known Limits (declared, not defects)

- `complete` is always `false` while Phase 25 (reconciliation) is absent.
- Card-billed renewals are never matched to a card cycle; always `card_charge_projected`.
- Paid rows are checked for a missing posting only in the last 90 days.
- On mobile the Dashboard scrolls horizontally because of pre-existing chart sections (not part of this phase).

## Validation Sign-Off

- [x] All backend tasks have an automated verify command
- [x] No 3 consecutive tasks without automated verification
- [x] No watch-mode flags
- [x] `nyquist_compliant: true` set: every requirement has automated verification; the remaining manual checks are visual or data-dependent

**Approval:** approved 2026-10-17

## Validation Audit 2026-10-17

| Metric | Count |
|--------|-------|
| Gaps found | 1 (rendered UI, D-04) |
| Resolved | 1 (7 component tests) |
| Escalated to manual-only | 0 (visual checks listed above stay manual) |
