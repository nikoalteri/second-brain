---
phase: 16
slug: proof-first-validation-of-structural-finance-surfaces
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-04-30
---

# Phase 16 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11.5 via Laravel test runner |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test tests/Feature/Api/CreditCardApiTest.php tests/Feature/Api/AccountApiTest.php` |
| **Full suite command** | `composer test` |
| **Estimated runtime** | ~180 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test tests/Feature/Api/CreditCardApiTest.php tests/Feature/Api/AccountApiTest.php`
- **After every plan wave:** Run `php artisan test tests/Feature/Api/CreditCardApiTest.php tests/Feature/Api/AuthApiTest.php tests/Feature/Api/AccountApiTest.php`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 16-01-01 | 01 | 1 | P16-CC-01 | feature/integration | `php artisan test tests/Feature/CreditCardLifecycleIntegrationTest.php tests/Feature/CreditCardExpenseIntegrationTest.php` | ✅ | ⬜ pending |
| 16-01-02 | 01 | 1 | P16-CC-02, P16-CC-03 | feature/api | `php artisan test tests/Feature/Api/CreditCardApiTest.php` | ✅ | ⬜ pending |
| 16-02-01 | 02 | 2 | P16-CC-04, P16-CC-05 | feature/integration | `php artisan test tests/Feature/CreditCardLifecycleIntegrationTest.php` | ✅ | ⬜ pending |
| 16-02-02 | 02 | 2 | P16-CC-04, P16-CC-05 | feature/api + docs | `php artisan test tests/Feature/Api/CreditCardApiTest.php tests/Feature/CreditCardLifecycleIntegrationTest.php && grep -n "credit card" .planning/phases/13-current-state-audit/13-VALIDATED-CAPABILITIES.md && grep -nE "structural-only|validated|credit-card|credit card" .planning/phases/13-current-state-audit/13-CURRENT-STATE-AUDIT.md` | ✅ | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

Existing infrastructure covers all phase requirements.

---

## Manual-Only Verifications

All phase behaviors have automated verification.

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [x] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-04-30
