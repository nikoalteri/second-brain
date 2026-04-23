---
phase: 10
slug: localization-foundation-shared-settings
status: draft
nyquist_compliant: true
wave_0_complete: true
created: 2026-04-23
---

# Phase 10 — Validation Strategy

> Per-phase validation contract for feedback sampling during execution.

---

## Test Infrastructure

| Property | Value |
|----------|-------|
| **Framework** | PHPUnit 11 via Laravel test runner |
| **Config file** | `phpunit.xml` |
| **Quick run command** | `php artisan test tests/Feature/Api/AuthApiTest.php` |
| **Full suite command** | `php artisan test` |
| **Estimated runtime** | ~60 seconds |

---

## Sampling Rate

- **After every task commit:** Run `php artisan test tests/Feature/Api/AuthApiTest.php`
- **After every plan wave:** Run `php artisan test`
- **Before `/gsd-verify-work`:** Full suite must be green
- **Max feedback latency:** 60 seconds

---

## Per-Task Verification Map

| Task ID | Plan | Wave | Requirement | Test Type | Automated Command | File Exists | Status |
|---------|------|------|-------------|-----------|-------------------|-------------|--------|
| 10-01-01 | 01 | 1 | I18N-01/I18N-02 | feature | `php artisan test tests/Feature/Localization/FallbackBehaviorTest.php --stop-on-failure` | ❌ W0 | ⬜ pending |
| 10-01-02 | 01 | 1 | I18N-03 | feature | `php artisan test tests/Feature/Api/AuthApiTest.php --stop-on-failure` | ✅ partial | ⬜ pending |
| 10-02-01 | 02 | 2 | I18N-02/I18N-04 | feature | `php artisan test tests/Feature/Localization/LocaleMiddlewareTest.php --stop-on-failure` | ❌ W0 | ⬜ pending |
| 10-03-01 | 03 | 2 | I18N-01/I18N-03/I18N-04 | node smoke + build | `node tests/Frontend/i18n-fallback-smoke.mjs && npm run build` | ❌ W0 | ⬜ pending |
| 10-04-01 | 04 | 3 | I18N-12/I18N-13 | Filament feature | `php artisan test tests/Feature/Filament/ProfileLocaleSettingsTest.php --stop-on-failure` | ❌ W0 | ⬜ pending |
| 10-05-01 | 05 | 4 | I18N-03/I18N-12/I18N-13 | manual gate + automated smoke | `php artisan test tests/Feature/Localization/FallbackBehaviorTest.php --stop-on-failure && php artisan test tests/Feature/Localization/LocaleMiddlewareTest.php --stop-on-failure && php artisan test tests/Feature/Filament/ProfileLocaleSettingsTest.php --stop-on-failure && npm run build` | ✅ by prior plans | ⬜ pending |

*Status: ⬜ pending · ✅ green · ❌ red · ⚠️ flaky*

---

## Wave 0 Requirements

- [ ] `resources/js/i18n/index.js` — SPA i18n bootstrap with English fallback
- [ ] `tests/Frontend/i18n-fallback-smoke.mjs` — missing SPA key fallback coverage
- [ ] `tests/Feature/Localization/LocaleMiddlewareTest.php` — backend locale resolution and fallback coverage
- [ ] `tests/Feature/Filament/ProfileLocaleSettingsTest.php` — backend language settings surface and persistence coverage
- [ ] `tests/Feature/Localization/FallbackBehaviorTest.php` — missing-key fallback behavior for frontend/backend foundations

---

## Manual-Only Verifications

| Behavior | Requirement | Why Manual | Test Instructions |
|----------|-------------|------------|-------------------|
| Filament UI visibly changes locale for authenticated users after saving language | I18N-12/I18N-13 | Package/vendor UI strings and Livewire request behavior are best confirmed in-browser | Login to Filament, change language in backend settings, reload and navigate core pages, confirm Italian/English UI changes persist |
| SPA boot picks up updated language after backend-side change | I18N-03/I18N-13 | Cross-surface session/user-flow verification spans both frontend and backend | Change language in backend, open SPA, refresh auth-backed page, confirm selected language is applied without duplicate configuration |
| Backend profile reflects a language change made from SPA settings | I18N-03/I18N-12/I18N-13 | Cross-surface round-trip behavior is easiest to confirm in-browser | Change language from SPA settings, save, reload `/admin/profile`, and confirm the same value is preselected there without extra setup |

---

## Validation Sign-Off

- [x] All tasks have `<automated>` verify or Wave 0 dependencies
- [x] Sampling continuity: no 3 consecutive tasks without automated verify
- [x] Wave 0 covers all MISSING references
- [ ] No watch-mode flags
- [x] Feedback latency < 60s
- [x] `nyquist_compliant: true` set in frontmatter

**Approval:** approved 2026-04-23
