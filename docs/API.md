# 🔌 API Documentation — Fluxa

**Status:** Implemented  
**Last Updated:** 2026-04-23

---

## Overview

Fluxa now exposes a **mixed REST + GraphQL API**:

- **REST** is the primary integration surface for SPA-critical finance flows
- **GraphQL** is still used for selected aggregates and legacy finance queries already wired into the app

**Authentication:** Bearer token flow via Laravel API auth endpoints  
**Generated docs:** Scribe output plus OpenAPI artifact  
**Published OpenAPI file:** `public/vendor/scribe/openapi.yaml`

---

## Current API shape

### REST

REST covers the operational finance workflows used by the SPA:

- authentication (`login`, `me`, `refresh`, `logout`)
- accounts
- transactions
- loans
- credit cards, cycles, expenses, and payments
- subscriptions
- subscription frequencies
- dashboard reminders
- dashboard charts
- finance report endpoints

### GraphQL

GraphQL remains in use for selected finance aggregate queries and compatibility with existing frontend logic, including:

- monthly cashflow
- totals by category
- existing finance entity queries still retained in the app

---

## Authentication

Use a Bearer token obtained from:

```http
POST /api/v1/auth/login
```

The authenticated API surface also provides:

- `GET /api/v1/auth/me`
- `POST /api/v1/auth/refresh`
- `POST /api/v1/auth/logout`

---

## Important finance endpoints

### Dashboard

- `GET /api/v1/dashboard/upcoming-payments`
- `GET /api/v1/dashboard/charts`

These support:

- loan, credit-card, and subscription reminders
- cashflow chart data
- expense-category breakdowns
- net-worth trend data

### Subscriptions

- `GET /api/v1/subscriptions`
- `POST /api/v1/subscriptions`
- `GET /api/v1/subscription-frequencies`

These support:

- backend-managed billing frequencies
- account-backed renewals
- credit-card-backed renewals
- automated renewal posting

### Finance reports

- `GET /api/v1/reports/finance`
- `GET /api/v1/reports/finance/details`

---

## Rate limiting

The API uses separate read/write rate limits:

- **read endpoints:** `throttle:api-read`
- **write endpoints:** `throttle:api-write`

---

## Source of truth

For live endpoint examples and payloads, use the generated Scribe output rather than this summary file.

Regenerate docs with:

```bash
php artisan scribe:generate
```
