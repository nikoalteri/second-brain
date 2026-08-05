---
phase: 17
slug: custom-read-only-finance-chatbot-engine
status: ready
nyquist_compliant: true
wave_0_complete: true
created: 2026-08-05
---

# Phase 17 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.5.3, Laravel Feature test helpers (`Sanctum::actingAs`, `RefreshDatabase`) — backend only. No frontend JS test framework configured project-wide (pre-existing gap, not introduced by this phase). |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test --filter=Chatbot` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~10 seconds (quick) / ~10 seconds (full, current baseline ~7-8s for 122 tests) |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test --filter=Chatbot`
- **After every plan wave:** Run `php artisan test` (full backend suite — ensures no regression in `DashboardApiTest`/`FinanceReportApiTest` if the upcoming-payments/cashflow extraction refactor touches shared logic)
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** ~10 seconds
- **Frontend (widget/store):** No automated gate exists project-wide. Manual verification checklist against `17-UI-SPEC.md` (copy, color, spacing contract).

---

## Per-Task Verification Map

| Decision | Behavior | Test Type | Automated Command | File Exists | Status |
|----------|----------|-----------|-------------------|-------------|--------|
| D-01 | Chatbot rejects any intent outside the 3 validated ones (out-of-scope response, never queries structural-only domains) | feature | `php artisan test --filter=test_chatbot_rejects_unsupported_intent` | ❌ W0 | ⬜ pending |
| D-07.1 | Account balances intent returns only the authenticated user's own accounts, correct balance values | feature | `php artisan test --filter=test_chatbot_account_balances_intent_returns_scoped_accounts` | ❌ W0 | ⬜ pending |
| D-07.1 (cross-user) | Account balances intent never leaks another user's account data | feature | `php artisan test --filter=test_chatbot_account_balances_intent_is_user_scoped` | ❌ W0 | ⬜ pending |
| D-07.2 | Upcoming payments intent returns the same data shape/values as `DashboardController::upcomingPayments` for an identical fixture | feature | `php artisan test --filter=test_chatbot_upcoming_payments_matches_dashboard` | ❌ W0 | ⬜ pending |
| D-07.3 | Monthly spending intent returns correct current-month earnings/expenses/net figures | feature | `php artisan test --filter=test_chatbot_monthly_spending_intent_returns_correct_totals` | ❌ W0 | ⬜ pending |
| Auth boundary | Unauthenticated request to `/api/v1/chatbot/ask` returns 401 | feature | `php artisan test --filter=test_chatbot_ask_requires_authentication` | ❌ W0 | ⬜ pending |
| Rate limiting | `chatbot/ask` is subject to `throttle:api-read`, not a separate/looser limiter | feature / manual | `php artisan route:list --path=chatbot` + optional throttle test | ❌ W0 | ⬜ pending |
| IntentRouter unit behavior | Router throws `UnsupportedIntentException` for unknown keys; dispatches to correct handler for known keys | unit | `php artisan test --filter=IntentRouterTest` | ❌ W0 | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

*No formal REQ-IDs exist for this phase (predates formal requirement-ID mapping). Map is derived from CONTEXT.md decisions D-01/D-07/D-08 instead.*

---

## Wave 0 Requirements

- [ ] `tests/Feature/Api/ChatbotApiTest.php` — covers D-01, D-07.1, D-07.2, D-07.3, auth boundary
- [ ] `tests/Unit/Services/Chatbot/IntentRouterTest.php` — covers router dispatch/unsupported-intent behavior in isolation
- [ ] Extraction refactor tests: if `UpcomingPaymentsIntent` and `DashboardController::upcomingPayments()` are unified behind a shared service method, extend `tests/Feature/Api/DashboardApiTest.php` to confirm no regression, and mirror equivalent coverage in the new chatbot test file

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Chat widget visual/copy compliance | D-06, UI-SPEC | No frontend automated test framework configured project-wide | Open the SPA, trigger the floating widget on multiple pages, compare against `17-UI-SPEC.md` (spacing/typography/color/copy) |
| Widget appears on every authenticated page (D-06) | D-06 | Requires visually walking authenticated routes; RESEARCH.md Open Question 1 flags this as not exhaustively traced | Navigate to each authenticated SPA route and confirm the floating trigger renders |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 10s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-08-06 (gsd-plan-checker: VERIFICATION PASSED, no blockers)
