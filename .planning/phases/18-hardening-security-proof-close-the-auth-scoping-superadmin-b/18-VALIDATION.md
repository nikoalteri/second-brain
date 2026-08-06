---
phase: 18
slug: hardening-security-proof-close-the-auth-scoping-superadmin-b
status: ready
nyquist_compliant: true
wave_0_complete: true
created: 2026-08-06
---

# Phase 18 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit (Laravel 12 test runner); attributes-based `#[Test]` and `test_` method-name convention coexist in this codebase |
| **Config file** | `phpunit.xml` (project root) — test DB is SQLite `:memory:`, no true parallel-connection concurrency possible |
| **Quick run command** | `php artisan test --filter=<TestClass>` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~8-10 seconds (SQLite in-memory, no external services) |

---

## Sampling Rate

- **After every task commit:** Targeted `--filter=<TestClass>` run for the file(s) touched by that task
- **After every plan wave:** Full `php artisan test` — Wave 2 (18-05) and Wave 3 (18-06) both share the credit-card cycle/observer chain touched by Wave 1's 18-04, so a full-suite regression check matters at every wave boundary
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** ~10 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Decision | Secure Behavior | Test Type | Automated Command | File Exists | Status |
|---------|------|------|----------|-----------------|-----------|-------------------|-------------|--------|
| 18-01-01 | 01 | 1 | D-05 | `transactionCategories` resolver applies owner scoping (superadmin bypass excepted) | feature | `php -l app/GraphQL/Queries/TransactionCategories.php && grep -c "hasRole('superadmin')" app/GraphQL/Queries/TransactionCategories.php` | ✅ modify | ✅ green |
| 18-01-02 | 01 | 1 | D-02/D-03 | Leak-locking test replaced with cross-user isolation assertions | feature | `php artisan test --filter=GraphQLApiTest` | ✅ modify | ✅ green |
| 18-01-03 | 01 | 1 | D-05 | `totalByCategory` resolver bypass isolated per non-superadmin user | feature | `php artisan test --filter=GraphQLApiTest` | ✅ modify | ✅ green |
| 18-02-01 | 02 | 1 | D-01/D-05 | Generic cross-user leak sweep across all 11 `HasUserScoping` models | feature | `php artisan test --filter=ScopingSecurityTest` | ❌ W0 (new) | ✅ green |
| 18-02-02 | 02 | 1 | D-05 | Dashboard charts + Finance report detail bypass sites reject cross-user reads | feature | `php artisan test --filter=DashboardApiTest && php artisan test --filter=FinanceReportApiTest` | ✅ modify | ✅ green |
| 18-02-03 | 02 | 1 | D-05 | Filament admin-panel record-binding rejects cross-user access (7 resources) | feature | `php artisan test --filter=AdminPanelScopingTest` | ❌ W0 (new) | ✅ green |
| 18-03-01 | 03 | 1 | D-04 | `credit-cards:generate-cycles` processes every user's cards (ambient no-op scoping by design) | feature | `php artisan test --filter=ConsoleScopingTest` | ❌ W0 (new) | ✅ green |
| 18-03-02 | 03 | 1 | D-04 | `loans:sync-installments` and `subscriptions:sync-renewals` process every user's records | feature | `php artisan test --filter=ConsoleScopingTest` | ❌ W0 (new) | ✅ green |
| 18-04-01 | 04 | 1 | D-01/D-03 | Sequenced duplicate-sync reproduces the balance-drift race (RED) | unit | `php artisan test --filter=CreditCardCycleServiceTest` | ✅ modify | ✅ green (RED confirmed) |
| 18-04-02 | 04 | 1 | D-02 | `syncCycleAndCardFromPayment` made idempotent via balance recompute-from-source (GREEN) | unit + full suite | `php artisan test` | ✅ modify | ✅ green |
| 18-04-03 | 04 | 1 | D-01/D-03 | End-to-end lifecycle regression for repeated mark-paid requests | feature | `php artisan test --filter=CreditCardLifecycleIntegrationTest` | ✅ modify | ✅ green |
| 18-05-01 | 05 | 2 | D-02 | Expense validation fails closed on a missing target credit card | feature | `php artisan test --filter=CreditCardExpenseIntegrationTest` | ✅ modify | ✅ green |
| 18-05-02 | 05 | 2 | D-01 | Observer static-state residue measured; intra-request contamination attempted | unit | `php artisan test --filter=ObserverStaticStateTest` | ❌ W0 (new) | ✅ green |
| 18-05-03 | 05 | 2 | D-02 | D-02 severity decision applied to the observer finding (fix or explicit defer) branch | feature + full suite | `php artisan test` | ✅ modify | ✅ green |
| 18-06-01 | 06 | 3 | D-02 | Every lower-severity finding from 18-01..18-05 recorded with fix-vs-defer rationale | docs | `test -f .../deferred-items.md && grep -c "Scope decision" .../deferred-items.md` | ❌ W0 (new) | ✅ green |
| 18-06-02 | 06 | 3 | D-05 | `CONCERNS.md` re-grounded against verified current code; full-suite phase gate | full suite | `php artisan test` | ✅ modify | ✅ green |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [x] `tests/Feature/ScopingSecurityTest.php` — generic cross-user-leak sweep (D-05, 18-02)
- [x] `tests/Feature/Filament/AdminPanelScopingTest.php` — Filament panel cross-user record-binding (D-05, 18-02)
- [x] `tests/Feature/ConsoleScopingTest.php` — non-HTTP scheduled-command scoping proof (D-04, 18-03)
- [x] `tests/Unit/Observers/ObserverStaticStateTest.php` — observer static-state reentrancy proof (18-05)
- [x] `.planning/phases/18-.../deferred-items.md` — lower-severity finding register (18-06)

No framework/config install needed — existing PHPUnit + Laravel test kernel covers all phase requirements.

---

## Manual-Only Verifications

*None — all phase behaviors have automated verification. This is a backend-only hardening phase; no frontend/visual behavior requires manual checkpoints.*

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 10s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-08-06 (gsd-plan-checker: 1 blocker — missing VALIDATION.md — resolved by creating this file; all other dimensions PASS, no plan content changes required)
