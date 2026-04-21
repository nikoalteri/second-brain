# Fluxa — Personal Finance Tracker

**Project Type:** Laravel application — finance tracker  
**Current Version:** v1.0 (Finance Backend complete)  
**Last Updated:** 2026-04-21

---

## What This Is

Fluxa is an admin-first personal finance tracker. It provides a unified system to monitor accounts, transactions, loans, credit cards, and subscriptions with business logic strongly typed and fully testable.

**Core Value:**
- Centralize all personal finance workflows in one system
- Keep business logic strongly typed and testable (services + enums + policies)
- Enable future API access for mobile and external integrations
- Provide real-time visibility into financial KPIs

---

## Current Milestone: v2.0

**Goal:** Enable off-admin access via REST/GraphQL APIs.

**Target features:**
- GraphQL schema + REST endpoints for all finance domains
- JWT authentication with token refresh
- Rate limiting and API documentation

---

## Previous Milestones: Delivered

### v1.0 — Finance Backend ✅
- Phase 0: Setup & Architecture
- Phase 1: Accounts & Transactions (dual-entry bookkeeping)
- Phase 2: Subscriptions (renewal tracking, cost calculations)
- Phase 3: Loans (amortization schedules, payment posting)
- Phase 4: Credit Cards (cycles, revolving credit, KPIs)
- Phase 5: Finance Dashboard & Reports

---

## Architecture & Technical Decisions

### Platform Foundation
- **Laravel 12** + **Filament 4** for admin CRUD and policy-based authorization
- **Lighthouse GraphQL** for schema-driven API contracts (Phase 2)
- **Tailwind CSS** for responsive UI
- **Spatie Permission** for role-based access control (RBAC)

### Core Patterns
- **Service Layer:** Business logic isolated, testable, and UI-agnostic
- **Enum-Driven State:** Type-safe status/type definitions for domain modeling
- **Observers + Jobs:** Async orchestration for expensive side effects
- **Policy-Based Authorization:** Centralized security rules for admin and API access
- **HasUserScoping Trait:** Applied to all user-owned models for automatic data isolation

### Finance Domain
All finance entities (Account, Transaction, Loan, CreditCard, Subscription) follow the same pattern:
1. Migration with `user_id` FK
2. Model with `HasUserScoping` + `SoftDeletes`
3. Enums for status/type fields
4. Service class for business logic
5. Observer for side effects
6. Policy for authorization
7. Filament Resource for admin CRUD
8. Factory for testing
9. Unit tests for service + auth tests for policy

---

## Team & Process

- Solo project — no external contributors
- GitHub for version control
- PHPUnit for testing (run `php artisan test`)
- Filament admin panel as primary UI
