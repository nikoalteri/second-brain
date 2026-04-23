# Fluxa — Roadmap

**Project:** Fluxa Personal Finance Tracker  
**Last Updated:** 2026-04-23

---

## v1.0 — Finance Backend ✅ (Complete)

### Phase 0: Setup & Architecture ✅
**Goal:** Bootstrap Laravel project with Filament admin, RBAC, and core infrastructure.

### Phase 1: Accounts & Transactions ✅
**Goal:** Implement bank accounts, transaction CRUD, categories, and balance tracking.

### Phase 2: Subscriptions ✅
**Goal:** Track recurring subscriptions with billing cycles, costs, and renewal reminders.

### Phase 3: Loans ✅
**Goal:** Manage loans with amortization schedules, payment posting, and balance tracking.

### Phase 4: Credit Cards ✅
**Goal:** Track credit cards with revolving credit cycles, expenses, payments, and interest calculation.

### Phase 5: Finance Dashboard & Reports ✅
**Goal:** Unified finance dashboard with KPIs, charts, and exportable reports across all modules.

---

## v2.0 — API Layer ✅ (Complete)

### Phase 6: REST + GraphQL API ✅
**Goal:** Expose all finance data (accounts, transactions, subscriptions, loans, credit cards) via a secure REST + GraphQL API with JWT authentication, rate limiting, and auto-generated documentation.

**Plans:** 7/7 plans complete ✅

Plans:
- [x] 06-PLAN-1-foundation.md — Config fixes (sanctum guard, lighthouse guard, sanctum expiry), install spatie/laravel-query-builder + scribe, register named rate limiters
- [x] 06-PLAN-2-auth-error-handling.md — AuthController (login/refresh/logout), routes/api.php v1 structure with throttle groups, exception handler JSON responses
- [x] 06-PLAN-3-accounts-transactions.md — AccountController + TransactionController with QueryBuilder filters/sorts/cursor-pagination, JSON resources, form requests
- [x] 06-PLAN-4-loans-creditcards-subscriptions.md — LoanController + CreditCardController + SubscriptionController with QueryBuilder, JSON resources, form requests
- [x] 06-PLAN-5-graphql.md — Full GraphQL schema (all finance types, queries with @with/@paginate, mutations with @create/@update/@delete), MonthlyCashflow + TotalByCategory custom resolvers
- [x] 06-PLAN-6-api-docs.md — Configure Scribe for api/v1/* only, add controller docblocks, generate OpenAPI 3.0 at /docs
- [x] 06-PLAN-7-tests.md — Feature tests for auth, REST CRUD + scoping + pagination + filters, GraphQL queries + mutations

---

## v3.0 — Frontend ✅ (Delivered)

### Phase 7: Mobile-Friendly Frontend SPA ✅
**Goal:** Build a mobile-friendly Vue 3 SPA (Composition API + script setup) served within the existing Laravel project, consuming the REST + GraphQL APIs with shared auth, responsive UI, and full finance CRUD coverage.

---

## v4.0 — Advanced Analytics ⏳

### Phase 8: Budget Planning & Exports ⏳
**Goal:** Add optional category budgets, in-app budget alerts, and exportable PDF/CSV/Excel reports for existing finance report data.

### Phase 9: Bank Feed Integrations
**Goal:** Integrate with bank APIs (Open Banking / PSD2) for automatic transaction import.

---

## v5.0 — Localization & Unified Settings ⏳

### Phase 10: Localization Foundation & Shared Settings ⏳
**Goal:** Establish the English/Italian localization infrastructure, safe fallback behavior, and one shared per-user language preference editable from both frontend and backend.

### Phase 11: Frontend Localization Rollout ⏳
**Goal:** Translate the SPA's navigation, settings, finance workflows, validation copy, reports, and dashboards so the current frontend is usable in English and Italian.

### Phase 12: Backend Localization Rollout & Verification ⏳
**Goal:** Translate the Filament backend UI, expose useful backend language controls, and verify that language preference behavior works consistently across frontend and backend sessions.

**Success Criteria:**
1. English and Italian both work across the SPA
2. English and Italian both work across the Filament backend
3. Users can change language from frontend and backend settings
4. One shared saved preference drives both surfaces
5. Existing users fall back safely to English
6. Regression coverage protects localization behavior across settings, auth, and finance flows
