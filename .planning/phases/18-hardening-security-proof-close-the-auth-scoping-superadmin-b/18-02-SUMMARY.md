---
phase: 18-hardening-security-proof-close-the-auth-scoping-superadmin-b
plan: 02
subsystem: testing/security
tags: [scoping, auth, superadmin, filament, dashboard, finance-reports, regression-tests]
dependency-graph:
  requires: []
  provides:
    - "Generic HasUserScoping cross-user leak sweep (tests/Feature/ScopingSecurityTest.php)"
    - "Targeted REST bypass-site regression tests (DashboardApiTest, FinanceReportApiTest)"
    - "Filament route-model-binding cross-user regression tests (AdminPanelScopingTest)"
  affects:
    - "app/Traits/HasUserScoping.php (now asserted, not just assumed)"
    - "app/Http/Controllers/Api/V1/DashboardController.php"
    - "app/Services/FinanceReportService.php"
    - "app/Filament/Resources/Accounts/AccountsResource.php"
    - "app/Filament/Resources/Notifications/NotificationResource.php"
    - "app/Filament/Resources/UserSettings/UserSettingResource.php"
tech-stack:
  added: []
  patterns:
    - "Pattern-based seed-pair sweep over a class-map so a 12th HasUserScoping model requires only one new seed entry"
    - "Own-record-200-before-foreign-record-404 sequencing in Filament binding tests to rule out false-positive blanket denial"
key-files:
  created:
    - tests/Feature/ScopingSecurityTest.php
    - tests/Feature/Filament/AdminPanelScopingTest.php
  modified:
    - tests/Feature/Api/DashboardApiTest.php
    - tests/Feature/Api/FinanceReportApiTest.php
decisions:
  - "No leaks were found: all 11 HasUserScoping models correctly isolate cross-user reads, the two non-GraphQL withoutGlobalScopes() disclosure sites correctly re-apply their own gate, and all 7 Filament resources correctly rely on the user global scope for route-model binding (only AccountsResource overrides getEloquentQuery(), matching RESEARCH.md's corrected 2026-08-06 finding)."
  - "Used distinct TransactionCategory names per user in the two new Dashboard chart tests, because the existing chart-grouping logic groups expense categories by name; reusing the factory's default 'General' category name for both users would have merged their totals and made the cross-user amounts indistinguishable in the response body."
metrics:
  duration: 24m
  completed: 2026-08-06
---

# Phase 18 Plan 02: Prove Auth-Scoping and Superadmin-Bypass Boundaries Summary

Added a generic pattern-based cross-user leak sweep across all 11 `HasUserScoping` models, targeted regression tests on the two non-GraphQL `withoutGlobalScopes()` disclosure sites (Dashboard charts, Finance report details), and Filament route-model-binding cross-user tests for 3 representative resources plus a superadmin-bypass check — turning the tenant boundary into an asserted contract instead of an assumption.

## What Was Built

**Task 1 — `tests/Feature/ScopingSecurityTest.php`:**
A `seedPair()` helper seeds one row per user (userA, userB) across all 11 `HasUserScoping` models (`Account`, `AuditLog`, `Backup`, `CategoryBudget`, `CreditCard`, `Loan`, `Notification`, `Subscription`, `Transaction`, `TransactionCategory`, `UserSetting`), using factories where they exist and `withoutGlobalScopes()->create([...])` for the 5 models without factories (`AuditLog`, `Backup`, `Notification`, `TransactionCategory`, `UserSetting`). Three tests iterate the resulting class-map:
1. Non-superadmin authenticated reads see only their own row per model, never the other user's (`assertNotContains`).
2. Superadmin reads see both users' rows per model.
3. Unauthenticated (console) context sees both users' rows per model — documenting the intentional no-op that scheduled commands depend on (D-04).

All 3 tests pass; no leaks were found across any of the 11 models.

**Task 2 — `tests/Feature/Api/DashboardApiTest.php` and `tests/Feature/Api/FinanceReportApiTest.php`:**
Added `test_dashboard_charts_does_not_include_other_users_expenses`, `test_superadmin_dashboard_charts_includes_all_users_expenses`, and `test_finance_report_details_does_not_return_other_users_transactions`. These assert the `Transaction::withoutGlobalScopes()` bypass sites in `DashboardController::getExpenseCategoriesChartData()` and `FinanceReportService::getDetailTransactions()` correctly re-apply their own `$request->user()->id` / `$userId` gate for non-superadmins, and correctly bypass it for superadmins. All pass.

**Task 3 — `tests/Feature/Filament/AdminPanelScopingTest.php`:**
A `panelUser()` helper builds a non-superadmin panel user with `module.adminpanel` permission. Four tests assert: a panel user can open their own Account/Notification/UserSetting edit page (200) but gets 404 on another user's record of the same type (route-model binding filtered by the `HasUserScoping` global scope, which the resources' `withoutGlobalScopes([SoftDeletingScope::class])` call does not remove), and a superadmin can open a foreign Account record (200). All 4 pass on the first run — no permission gaps or unexpected 403s were encountered, so the plan's fallback guidance (accepting 403 with documentation) was not needed.

## Deviations from Plan

None — plan executed exactly as written, with one implementation detail not explicitly spelled out in the plan: the Dashboard chart tests needed each user's transaction assigned to a distinctly-named `TransactionCategory` (`DashboardCatA` / `DashboardCatB`) rather than relying on `TransactionFactory`'s default category. The controller groups `expense_categories` by category *name*, and both users' factory-created transactions would otherwise default to a category literally named `General` (each user has their own row, but with the same name), causing their amounts to merge into a single summed group and making `111.11` and `999.99` indistinguishable in the response body. This is a test-construction detail (Rule 1 — bug in the test itself, not the code under test) required to make the plan's specified assertions ("assert 111.11 present / 999.99 absent") actually test what they claim to test.

## Findings

No cross-user data leaks were discovered in any of the three proof surfaces (generic model sweep, targeted REST bypass sites, Filament route-model binding). All `withoutGlobalScopes()` call sites covered by this plan's scope correctly re-apply their own tenant gate. Per D-02, no severity classification or fix is required since no leak was found.

## Out of Scope

`tests/Feature/Filament/FinanceReportPageTest.php::admin_finance_report_renders_budget_month_context_alerts_and_export_options` fails on a pre-existing, unrelated assertion (`assertSee('exceeded')` not found in rendered HTML). This file was not touched by this plan; confirmed via `git diff` against the plan's base commit that the file is unchanged, and the failure reproduces identically at the base commit. Out of scope per the plan's scope boundary — not fixed here, logged as a pre-existing issue for a future plan.

## Self-Check: PASSED

- FOUND: tests/Feature/ScopingSecurityTest.php
- FOUND: tests/Feature/Filament/AdminPanelScopingTest.php
- FOUND commit: 42e794d (Task 1)
- FOUND commit: dad065e (Task 2)
- FOUND commit: b06a2e6 (Task 3)
