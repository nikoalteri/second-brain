# Fluxa — Roadmap

**Project:** Fluxa Personal Finance Tracker  
**Last Updated:** 2026-04-21

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

**Success Criteria:**
1. JWT authentication working for all API endpoints
2. REST endpoints cover accounts, transactions, subscriptions, loans, credit cards
3. GraphQL schema mirrors REST surface with query/mutation support
4. Rate limiting enforced per user/IP (100 read / 20 write)
5. API documentation auto-generated (Scribe OpenAPI 3.0 at /docs)
6. All endpoints protected by existing RBAC policies
7. Test coverage for auth, CRUD, scoping, and error responses

---

## v3.0 — Frontend ⏳

### Phase 7: Mobile-Friendly Frontend SPA ⏳
**Goal:** Build a mobile-friendly Vue 3 SPA (Composition API + script setup) served within the existing Laravel project, consuming the v2.0 REST + GraphQL API. Covers all 5 finance domains with full CRUD, Apollo Client v4 with auto-refresh, Pinia auth store, and responsive dark-theme UI.

**Plans:** 5 plans

Plans:
- [ ] 07-01-infrastructure-auth-PLAN.md — npm install, Vite + Vue plugin, Blade entry, web.php catch-all, Apollo Client v4, Pinia auth store, router (20 routes + auth guard), Login view, all shared UI components + composables
- [ ] 07-02-dashboard-accounts-PLAN.md — Dashboard (KPI cards + cashflow chart + category totals), Accounts list/detail/form (CRUD)
- [ ] 07-03-transactions-PLAN.md — Schema additions (transactionTypes + transactionCategories queries), Transactions list with filters + create/edit form
- [ ] 07-04-loans-creditcards-PLAN.md — Loans list/detail/form with amber progress bars, Credit Cards list/detail/form with purple credit bars + cycle accordion
- [ ] 07-05-subscriptions-polish-PLAN.md — Subscriptions list/form with renewal highlighting, human verification checkpoint

---

## v4.0 — Advanced Analytics ⏳

### Phase 8: Budget Planning & Forecasting
**Goal:** Add budget planning, spending forecasts, and exportable PDF reports.

### Phase 9: Bank Feed Integrations
**Goal:** Integrate with bank APIs (Open Banking / PSD2) for automatic transaction import.
