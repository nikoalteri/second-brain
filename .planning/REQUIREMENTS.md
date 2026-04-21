# v2.0 Requirements — API Layer

**Milestone:** v2.0 — REST/GraphQL API  
**Phase Range:** Phase 6  
**Status:** Planned  
**Date Created:** 2026-04-21

---

## Phase 6: Finance API Layer — 20 Requirements

**Goal:** Enable off-admin access to all finance data via REST and GraphQL APIs with JWT authentication, rate limiting, and documentation.

### Authentication & Authorization (API-01 to API-05)

- [ ] **API-01:** User authenticates via email/password and receives a JWT access token (30 min) and refresh token (7 days)
- [ ] **API-02:** User refreshes an expired access token using a valid refresh token without re-authenticating
- [ ] **API-03:** User can logout and invalidate all tokens; subsequent requests with old tokens are rejected
- [ ] **API-04:** API enforces user_id scoping; user cannot access another user's data even with valid JWT
- [ ] **API-05:** API enforces rate limiting: 100 read/min, 20 write/min; requests above threshold return 429

### REST API — Finance (API-06 to API-10)

- [ ] **API-06:** CRUD on Accounts via REST (`/api/v1/accounts`) with balance tracking
- [ ] **API-07:** CRUD on Transactions via REST (`/api/v1/transactions`) with filtering by category, date range, amount
- [ ] **API-08:** CRUD on Loans via REST (`/api/v1/loans`) with nested payment access
- [ ] **API-09:** CRUD on Credit Cards via REST (`/api/v1/credit-cards`) with cycle and expense access
- [ ] **API-10:** CRUD on Subscriptions via REST (`/api/v1/subscriptions`) with renewal info

### REST API — Pagination, Sorting, Filtering (API-11 to API-13)

- [ ] **API-11:** Cursor-based pagination with configurable page size (default 20 items)
- [ ] **API-12:** Sorting by any indexed column via query parameter
- [ ] **API-13:** Filtering by multiple criteria (status, category, date range) with logical operators

### REST API — Error Handling (API-14)

- [ ] **API-14:** Consistent error responses: 400, 422 (with field details), 403, 404

### GraphQL API (API-15 to API-18)

- [ ] **API-15:** Query core finance types with nested relationships (no N+1 queries)
- [ ] **API-16:** Create/update/delete resources via GraphQL mutations with input validation
- [ ] **API-17:** Aggregated data queries (total by category, monthly cashflow) without N+1
- [ ] **API-18:** Schema fully documented with descriptions; introspection enabled

### API Documentation & Performance (API-19 to API-20)

- [ ] **API-19:** OpenAPI 3.0 spec available at `/api/docs` via Swagger UI
- [ ] **API-20:** All paginated endpoints respond in < 500ms; verified via eager loading
