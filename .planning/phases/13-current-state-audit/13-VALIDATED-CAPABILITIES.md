# Phase 13 Validated Capabilities

This ledger is the Phase 13 evidence base for what Fluxa ships today. It separates strongly proven capabilities from weaker structural inferences so later planning work does not overstate current behavior.

## Status key

- `validated` — directly backed by current code and current automated tests
- `structural-only` — visible in current code structure, but not re-confirmed by current automated proof

## Auth and settings

| Capability | Status | Proof type | Supporting files |
| --- | --- | --- | --- |
| SPA users can log in, receive access + refresh tokens, fetch profile data, log out, and update frontend settings through `/api/v1/auth/*`. | validated | code+test | `routes/api.php`, `resources/js/router/index.js`, `tests/Feature/Api/AuthApiTest.php` |
| Protected REST endpoints reject unauthenticated access with JSON `401` responses, and cross-user account access is blocked for normal users. | validated | code+test | `routes/api.php`, `tests/Feature/Api/AuthApiTest.php`, `tests/Feature/Api/AccountApiTest.php` |
| The SPA has authenticated routes for dashboard, profile, settings, finance report, accounts, transactions, loans, credit cards, and subscriptions. | structural-only | code | `resources/js/router/index.js`, `resources/js/views/` |

## Finance CRUD domains

| Capability | Status | Proof type | Supporting files |
| --- | --- | --- | --- |
| Accounts support REST list/show/create/update/delete with cursor pagination, filtering, sorting, authenticated user ownership, and superadmin cross-user access. | validated | code+test | `routes/api.php`, `tests/Feature/Api/AccountApiTest.php` |
| Transactions expose REST list/show/create/update/delete routes and SPA create/edit/list routes. | structural-only | code | `routes/api.php`, `resources/js/router/index.js` |
| Loans expose REST list/show/create/update/delete plus schedule generation routes, and SPA list/detail/create/edit routes. | structural-only | code | `routes/api.php`, `resources/js/router/index.js` |
| Credit cards now have targeted REST proof for owner-scoped list/show/update/delete access, foreign-account binding rejection, nested cycle/payment/expense parent checks, open-cycle issue, and mark-paid posting side effects. | validated | code+test | `routes/api.php`, `tests/Feature/Api/CreditCardApiTest.php`, `tests/Feature/CreditCardLifecycleIntegrationTest.php` |
| Broader credit-card SPA flows and untouched lifecycle depth beyond the proven REST issue-to-mark-paid slice remain lower-confidence. | structural-only | code | `resources/js/router/index.js`, `app/Services/CreditCardCycleService.php` |
| Subscriptions expose REST list/show/create/update/delete routes plus frequency lookup, and SPA list/create/edit routes. | structural-only | code | `routes/api.php`, `resources/js/router/index.js` |

## Dashboard, reporting, budgets, and exports

| Capability | Status | Proof type | Supporting files |
| --- | --- | --- | --- |
| Dashboard APIs return upcoming payments and chart data for cashflow, expense categories, and net worth trend. | validated | code+test | `routes/api.php`, `tests/Feature/Api/DashboardApiTest.php` |
| Finance report export supports CSV, XLSX, and PDF downloads and applies current report filters to finance data. | validated | code+test | `routes/api.php`, `tests/Feature/Api/FinanceReportExportApiTest.php` |
| The admin finance report page renders budget month/status context and export labels for CSV, XLSX, and PDF. | validated | code+test | `app/Providers/Filament/AdminPanelProvider.php`, `tests/Feature/Filament/FinanceReportPageTest.php` |
| Monthly budget read/update/delete endpoints exist under `/api/v1/budgets/monthly`. | structural-only | code | `routes/api.php` |

## Admin panel

| Capability | Status | Proof type | Supporting files |
| --- | --- | --- | --- |
| Fluxa ships a Filament admin panel at `/admin` with finance navigation links for accounts, transactions, subscriptions, loans, credit cards, and reports. | structural-only | code | `app/Providers/Filament/AdminPanelProvider.php` |
| Admin panel access is limited by user state/roles: inactive users and viewers are denied, while qualifying admin and superadmin users can access. | validated | code+test | `app/Providers/Filament/AdminPanelProvider.php`, `tests/Feature/Auth/FilamentPanelAccessTest.php` |
| The admin dashboard currently renders without the removed budget widget surface. | validated | code+test | `tests/Feature/Filament/FinanceReportPageTest.php` |

## GraphQL

| Capability | Status | Proof type | Supporting files |
| --- | --- | --- | --- |
| GraphQL exposes guarded queries and mutations for accounts, transactions, loans, credit cards, and subscriptions. | structural-only | code | `graphql/schema.graphql` |
| GraphQL also exposes guarded aggregate queries for monthly cashflow, category totals, transaction types, and transaction categories. | structural-only | code | `graphql/schema.graphql` |

## Current confidence boundary

- **Verification snapshot (2026-04-29 UTC):** `php artisan test tests/Feature/Api/AuthApiTest.php tests/Feature/Api/AccountApiTest.php tests/Feature/Api/DashboardApiTest.php tests/Feature/Api/FinanceReportExportApiTest.php tests/Feature/Filament/FinanceReportPageTest.php tests/Feature/Auth/FilamentPanelAccessTest.php`
- **Result:** 38 tests passed, 182 assertions, 0 failures
- **Re-confirmed by tests:** auth token flows and frontend settings, account CRUD/scoping/superadmin access, targeted credit-card REST scoping and issue-to-mark-paid behavior, dashboard charts/upcoming payments, finance report exports, admin finance report rendering, and admin panel access rules
- **Still code-only after re-run:** transaction REST behavior, loan CRUD behavior, subscription CRUD behavior, monthly budget API mutations, GraphQL claims, and untouched credit-card depth outside the proven REST slice

- This ledger intentionally marks GraphQL and several non-account finance CRUD surfaces as `structural-only` because the current Phase 13 proof set does not re-confirm them with targeted automated tests.
- Localization is intentionally excluded from active capability scope; Phase 13 treats prior localization planning as superseded history rather than a shipped current-state capability.
