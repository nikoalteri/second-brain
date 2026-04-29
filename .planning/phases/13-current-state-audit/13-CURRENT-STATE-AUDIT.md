# Phase 13 Current-State Audit

This audit uses `13-VALIDATED-CAPABILITIES.md` as the source of truth for what Fluxa ships today. Phase 13 stays documentation-only: it records current behavior, confidence limits, superseded history, and deferred planning concerns without turning the audit into cleanup work.

## Current shipped product surfaces

- **REST API:** `/api/v1` exposes auth/settings endpoints plus finance routes for accounts, transactions, loans, credit cards, subscriptions, budgets, dashboard data, and finance reports (`routes/api.php`).
- **Vue SPA:** authenticated routes exist for dashboard, profile, settings, reports, and the main finance domains (`resources/js/router/index.js`, `resources/js/views/`).
- **Filament admin panel:** `/admin` exists as a separate panel with finance navigation and a link back to the frontend (`app/Providers/Filament/AdminPanelProvider.php`).
- **GraphQL schema:** a guarded schema is present for core finance entities and aggregate queries (`graphql/schema.graphql`).

## Verification snapshot

- **Command:** `php artisan test tests/Feature/Api/AuthApiTest.php tests/Feature/Api/AccountApiTest.php tests/Feature/Api/DashboardApiTest.php tests/Feature/Api/FinanceReportExportApiTest.php tests/Feature/Filament/FinanceReportPageTest.php tests/Feature/Auth/FilamentPanelAccessTest.php`
- **Result:** 38 passing tests, 182 assertions, 0 failures
- **Re-confirmed by test proof:** auth/settings flows, account CRUD/scoping, dashboard charts and upcoming payments, finance exports, admin finance report rendering, and admin panel access control
- **Code-only after proof run:** GraphQL schema coverage, transaction CRUD behavior, loan CRUD behavior, credit-card lifecycle routes, subscription CRUD behavior, and monthly budget API mutations

## Validated capabilities by area

- **Auth and settings:** login, token refresh/logout, profile fetch, protected-route rejection, and frontend settings persistence are validated by current REST routes and `tests/Feature/Api/AuthApiTest.php`.
- **Accounts:** list/show/create/update/delete, filtering, sorting, user scoping, and superadmin access are validated by `tests/Feature/Api/AccountApiTest.php`.
- **Dashboard and reports:** upcoming payments, cashflow/category/net-worth chart responses, and CSV/XLSX/PDF finance exports are validated by `tests/Feature/Api/DashboardApiTest.php` and `tests/Feature/Api/FinanceReportExportApiTest.php`.
- **Admin panel:** finance report rendering, budget context/status display, export labels, and admin-panel access rules are validated by `tests/Feature/Filament/FinanceReportPageTest.php` and `tests/Feature/Auth/FilamentPanelAccessTest.php`.
- **Structural-only areas:** GraphQL, monthly budget API mutations, and most non-account CRUD domains remain present in code but are intentionally not promoted beyond the evidence level recorded in `13-VALIDATED-CAPABILITIES.md`.

## Confidence boundaries

- Phase 13 can confidently describe REST auth/settings, account workflows, dashboard/report exports, and admin access/report rendering as shipped behavior because those areas were re-confirmed by current tests.
- Phase 13 should describe transactions, loans, credit cards, subscriptions, monthly budget mutations, and GraphQL as **present in current code structure** unless later phases add stronger proof.
- No targeted proof failures were found, so no claim that was already marked `validated` required downgrade after the re-run.

## Superseded historical scope

The reverted localization milestone remains part of project history, but it is not active current scope. Current repository evidence shows English-only behavior in the SPA i18n layer: `resources/js/i18n/index.js` hard-resolves `en` / `en-US`, and `resources/js/i18n/messages/en.js` is the only current message bundle in `resources/js/i18n/messages/`.

That means Phase 13 should preserve localization work only as **superseded historical scope**:

- previous planning may mention English/Italian rollout goals
- current shipped behavior does **not** justify claiming bilingual frontend or backend support
- later roadmap work must not inherit localization as active scope unless a future milestone explicitly reintroduces it

## Planning-relevant concerns

- **Proof coverage gap:** GraphQL and several finance CRUD surfaces are still code-only in this audit, so later planning should avoid assuming they have the same confidence level as the tested REST/admin/reporting paths.
- **Scoping/security sensitivity:** `.planning/codebase/CONCERNS.md` notes reliance on auth-context scoping and superadmin bypass checks; future implementation phases should preserve those guarantees and add proof rather than treat them as settled everywhere.
- **Performance/fragility concerns remain deferred:** dashboard query volume, credit-card lifecycle fragility, and observer-chain risks are relevant for sequencing later cleanup phases, but Phase 13 does not schedule fixes.

## Phase 14 handoff

This audit is the evidence base for Phase 14 doc realignment. Phase 14 should rewrite top-level planning artifacts from the validated ledger and this audit, not from stale localization-era assumptions, and it should keep refactors and feature work out of scope.
