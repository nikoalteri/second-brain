---
gsd_state_version: 1.0
milestone: v1.0
milestone_name: — Finance Backend ✅
current_plan: 1
status: executing
stopped_at: Phase 7 UI-SPEC approved
last_updated: "2026-04-22T19:27:07.575Z"
progress:
  total_phases: 6
  completed_phases: 0
  total_plans: 0
  completed_plans: 0
---

# v2.0 Project State

**Project:** Fluxa — Personal Finance Tracker  
**Milestone:** v2.0 (API Layer)  
**Phases:** 6  
**Status:** Executing Phase 07
**Updated:** 2026-04-21

---

## Project Reference

**Core Value:**
Personal finance tracker built on Laravel + Filament. v1.0 delivered the complete finance backend (accounts, transactions, loans, credit cards, subscriptions). v2.0 adds REST/GraphQL API access for mobile clients.

## v1.0 Summary (Complete)

All finance backend phases delivered:

- Phase 0: Setup & Architecture ✅
- Phase 1: Accounts & Transactions ✅
- Phase 2: Subscriptions ✅
- Phase 3: Loans ✅
- Phase 4: Credit Cards ✅
- Phase 5: Finance Dashboard & Reports ✅

## Current Phase: 6 — API Layer

**Goal:** REST + GraphQL API with JWT auth, rate limiting, and OpenAPI docs.

**Current Plan:** 1

### Plans:

- Plan 1: API Foundation — Config Fixes, Package Installation, Rate Limiters ✅
- Plan 2: Auth + Error Handling ✅
- Plan 3: Accounts & Transactions REST endpoints ✅
- Plan 4: Loans, Credit Cards & Subscriptions REST endpoints ✅
- Plan 5: GraphQL API
- Plan 6: API Documentation
- Plan 7: Tests

## Decisions

- **2026-04-21:** Set Sanctum token expiration to 30 minutes (security default)
- **2026-04-21:** Named rate limiters registered in AppServiceProvider: api-read (100/min), api-write (20/min)
- **2026-04-21:** Lighthouse guards set to ['sanctum'] for Bearer token auth in GraphQL
- [Phase 06]: Renamed Subscription type to ServiceSubscription to avoid Lighthouse built-in Subscription conflict
- [Phase 06]: Replaced @scope directive with HasUserScoping global scope on all 5 finance models (Lighthouse 6 @scope is argument-only)
- [Phase 06]: Used type=laravel for Scribe so /docs route is served via Blade; copied openapi.yaml to public/docs/ for static access
- [Phase 06]: Cross-user access returns 404 not 403 due to HasUserScoping global scope filtering at route model binding

## Last Session

**Stopped at:** Phase 7 UI-SPEC approved
**Timestamp:** 2026-04-21T21:30:00Z
