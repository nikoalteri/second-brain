---
plan: 1
phase: 6
title: "API Foundation — Config Fixes, Package Installation, Rate Limiters"
status: complete
completed_at: 2025-07-14T10:00:00Z
subsystem: api
tags: [sanctum, lighthouse, rate-limiting, query-builder, scribe]
dependency_graph:
  requires: []
  provides: [sanctum-guard, lighthouse-sanctum, api-rate-limiters, query-builder, scribe]
  affects: [routes/api.php, graphql schema guards]
tech_stack:
  added: [spatie/laravel-query-builder@7.2.1, knuckleswtf/scribe@5.9.0]
  patterns: [named-rate-limiters, sanctum-guard, lighthouse-guards]
key_files:
  created: [config/sanctum.php, config/scribe.php]
  modified: [config/auth.php, config/lighthouse.php, app/Providers/AppServiceProvider.php, composer.json, composer.lock]
decisions:
  - "Set Sanctum token expiration to 30 minutes (security default)"
  - "Register named rate limiters (api-read: 100/min, api-write: 20/min) in AppServiceProvider"
  - "Removed deprecated ApiRateLimitMiddleware alias from boot()"
metrics:
  duration: "~15 minutes"
  completed_date: "2025-07-14"
---

# Phase 6 Plan 1: API Foundation — Config Fixes, Package Installation, Rate Limiters Summary

**One-liner:** Sanctum guard + Lighthouse guards configured, query-builder + scribe installed, named rate limiters registered at 100/20 per minute.

## What Was Done

### Task T1: Fix Sanctum + Lighthouse Configuration
- Published `config/sanctum.php` via `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` and set `'expiration' => 30`
- Added `sanctum` guard to `config/auth.php` guards array with `driver: sanctum`
- Fixed `config/lighthouse.php`: changed `'guards' => null` to `'guards' => ['sanctum']`

### Task T2: Install Packages + Register Named Rate Limiters
- Installed `spatie/laravel-query-builder` (v7.2.1) via Composer
- Installed `knuckleswtf/scribe` (v5.9.0) as dev dependency via Composer
- Published Scribe config generating `config/scribe.php`
- Added `use Illuminate\Cache\RateLimiting\Limit`, `use Illuminate\Http\Request`, `use Illuminate\Support\Facades\RateLimiter` imports to `AppServiceProvider`
- Registered `api-read` (100 req/min) and `api-write` (20 req/min) named rate limiters at the start of `boot()` method
- Removed deprecated `$this->app['router']->aliasMiddleware('api_rate_limit', ...)` line

## Key Changes

| File | Change |
|------|--------|
| `config/sanctum.php` | Created (published); `expiration` set to `30` minutes |
| `config/auth.php` | Added `sanctum` guard entry to `guards` array |
| `config/lighthouse.php` | Changed `'guards' => null` → `'guards' => ['sanctum']` |
| `config/scribe.php` | Created (published from Scribe package) |
| `app/Providers/AppServiceProvider.php` | Added 3 use imports; registered api-read/api-write rate limiters; removed ApiRateLimitMiddleware alias |
| `composer.json` | Added `spatie/laravel-query-builder` (^7.2) and `knuckleswtf/scribe` (^5.9, dev) |
| `composer.lock` | Updated lockfile for new packages |

## Package Versions

| Package | Version | Type |
|---------|---------|------|
| spatie/laravel-query-builder | 7.2.1 | production |
| knuckleswtf/scribe | 5.9.0 | dev |

## Verification Results

```
SANCTUM EXPIRATION OK     config/sanctum.php contains 'expiration' => 30
AUTH SANCTUM GUARD OK     config/auth.php contains 'driver' => 'sanctum'
LIGHTHOUSE GUARDS OK      config/lighthouse.php contains 'guards' => ['sanctum']
RATE LIMITER READ OK      AppServiceProvider contains RateLimiter::for('api-read'
RATE LIMITER WRITE OK     AppServiceProvider contains RateLimiter::for('api-write'
OLD ALIAS REMOVED OK      aliasMiddleware('api_rate_limit' not found in AppServiceProvider
SCRIBE CONFIG OK          config/scribe.php exists

php artisan config:clear  → INFO Configuration cache cleared successfully.
php artisan route:clear   → INFO Route cache cleared successfully.
php artisan inspire       → Quote output (no parse errors)
composer show spatie/laravel-query-builder → 7.2.1
composer show knuckleswtf/scribe          → 5.9.0
```

## Test Suite

**110 tests passing (254 assertions)** — no regressions introduced.

## Deviations from Plan

None - plan executed exactly as written.

## Self-Check: PASSED

- `config/sanctum.php` ✅ exists
- `config/auth.php` ✅ contains sanctum guard
- `config/lighthouse.php` ✅ guards = ['sanctum']
- `config/scribe.php` ✅ exists
- `app/Providers/AppServiceProvider.php` ✅ contains rate limiters, no old alias
- `composer.json` ✅ updated with both packages
- All 110 tests passing ✅
