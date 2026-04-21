---
plan: 2
phase: 6
title: "Authentication Endpoints + Error Handling + API Route Structure"
status: complete
completed_at: 2025-07-14T00:00:00Z
---

## What Was Done

### T1 — AuthController, Form Requests, and API Route Structure

Created the full authentication layer for the REST API:

- **`app/Http/Controllers/Api/V1/AuthController.php`** — Three endpoints: `login` (issues 30-minute access token + 7-day refresh token via Sanctum), `refresh` (rotates access token while preserving refresh token), and `logout` (deletes all tokens). Scribe-annotated for auto-docs.
- **`app/Http/Requests/Api/LoginRequest.php`** — Form request with `email` (required, string, email) and `password` (required, string, min:8) validation rules.
- **`app/Http/Requests/Api/RefreshRequest.php`** — Minimal form request for the refresh endpoint.
- **`routes/api.php`** — Complete v1 route skeleton: auth routes (login unauthenticated, refresh/logout behind `auth:sanctum`), read endpoints grouped under `throttle:api-read` (100/min), write endpoints grouped under `throttle:api-write` (20/min). Placeholder routes for AccountController, TransactionController, LoanController, CreditCardController, SubscriptionController (to be implemented in Plans 3–4).

### T2 — Exception Handler for JSON API Error Responses

Updated **`bootstrap/app.php`** `->withExceptions()` callback to return structured JSON error responses for all `api/*` routes or JSON-expecting requests:

- `ValidationException` → 422 with `message: "Validation failed."` + `errors` map
- `ModelNotFoundException` → 404 with `message: "Resource not found."`
- `AuthorizationException` → 403 with `message: "Forbidden."`
- `AuthenticationException` → 401 with `message: "Unauthenticated."`

### Deviation (Rule 3 — Blocking Fix)

`bootstrap/app.php` was missing the `api:` route registration in `->withRouting()`. Without this, `routes/api.php` would never be loaded and all API routes would return 404. Added `api: __DIR__ . '/../routes/api.php'` to the `withRouting()` call.

## Key Files Created/Modified

- `app/Http/Controllers/Api/V1/AuthController.php` (created)
- `app/Http/Requests/Api/LoginRequest.php` (created)
- `app/Http/Requests/Api/RefreshRequest.php` (created)
- `routes/api.php` (complete v1 rewrite)
- `bootstrap/app.php` (added api route registration + exception handlers)

## Verification Results

**T1:**
- ✅ `AuthController.php` exists with `login()`, `refresh()`, `logout()`
- ✅ `LoginRequest.php` exists with email + password rules
- ✅ `routes/api.php` contains `prefix('v1')`, `throttle:api-read`, `throttle:api-write`
- ✅ `routes/api.php` contains `AuthController::class, 'login'`
- ✅ `php artisan route:clear` exits 0

**T2:**
- ✅ Exception handler contains `$request->is('api/*')` check (×4)
- ✅ ValidationException handler returning 422
- ✅ ModelNotFoundException returning 404
- ✅ AuthorizationException returning 403
- ✅ AuthenticationException returning 401
- ✅ `php artisan config:clear` exits 0
- ✅ `php artisan inspire` exits 0 (no parse errors)

## Test Suite

All 110 tests passed (254 assertions) in 3.14s. No regressions introduced.
