# Fluxa — Roadmap

**Project:** Fluxa Personal Finance Tracker  
**Last Updated:** 2026-04-30

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

## v5.1 — Planning Realignment ⏳

### Phase 13: Current-State Audit ⏳
**Goal:** Convert the refreshed codebase map into a trusted statement of what the shipped product actually does today.

**Requirements:** [ALIGN-05]

**Plans:** 1/1 plans complete

Plans:
- [x] 13-01-capability-audit-PLAN.md — Build the evidence-backed current-state audit and superseded-scope note

**Success Criteria:**
1. Validated capabilities are inferred from the current codebase rather than stale milestone assumptions
2. Active product description matches the repository's real backend, SPA, and admin surfaces
3. Stale scope from reverted localization work is explicitly identified as inactive

### Phase 14: Planning Docs Realignment ⏳
**Goal:** Rewrite the active project artifacts so maintainers can trust `PROJECT.md`, `REQUIREMENTS.md`, and `STATE.md` again.

**Requirements:** [ALIGN-01, ALIGN-02, ALIGN-04]

**Success Criteria:**
1. `PROJECT.md` describes the current product and active milestone accurately
2. `REQUIREMENTS.md` contains only active scope grounded in the current codebase
3. `STATE.md` clearly communicates current focus, context, and next step

### Phase 15: Roadmap Reset & Concern Triage ⏳
**Goal:** Rebuild the near-term roadmap from current reality and separate deferred concerns from committed work.

**Requirements:** [ALIGN-03, ALIGN-06]

**Success Criteria:**
1. `ROADMAP.md` maps every active requirement to a real phase
2. Deferred concerns are captured explicitly without being mistaken for committed scope
3. The project ends this milestone with a clear next implementation/planning command
