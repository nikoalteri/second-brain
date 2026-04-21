---
plan: 1
phase: 6
title: "API Foundation — Config Fixes, Package Installation, Rate Limiters"
wave: 1
depends_on: []
requirements: [API-05]
files_modified:
  - config/auth.php
  - config/sanctum.php
  - config/lighthouse.php
  - app/Providers/AppServiceProvider.php
  - composer.json
  - composer.lock
autonomous: true

must_haves:
  truths:
    - "auth:sanctum middleware is recognized (no 401 due to unknown guard)"
    - "GraphQL @guard directive uses sanctum (Bearer token accepted)"
    - "Rate limiting returns 429 with standard X-RateLimit headers at 100 read / 20 write per user/IP"
    - "spatie/laravel-query-builder is available for QueryBuilder::for() usage in controllers"
    - "knuckleswtf/scribe is installed for OpenAPI generation"
  artifacts:
    - path: "config/sanctum.php"
      provides: "Sanctum config with expiration=30 minutes"
      contains: "'expiration' => 30"
    - path: "config/auth.php"
      provides: "sanctum guard registration"
      contains: "'driver' => 'sanctum'"
    - path: "config/lighthouse.php"
      provides: "Lighthouse sanctum guard config"
      contains: "'guards' => ['sanctum']"
    - path: "app/Providers/AppServiceProvider.php"
      provides: "Named rate limiters api-read (100/min) and api-write (20/min)"
      contains: "RateLimiter::for('api-read'"
  key_links:
    - from: "routes/api.php"
      to: "config/auth.php"
      via: "auth:sanctum middleware"
      pattern: "auth:sanctum"
    - from: "config/lighthouse.php"
      to: "config/sanctum.php"
      via: "'guards' => ['sanctum']"
      pattern: "guards.*sanctum"
---

## Objective

Fix three broken configuration gaps that would make every protected API endpoint return errors, and install the two packages needed for filtering and documentation. This must run before any controller or GraphQL work.

**Purpose:** Unblocks all Wave 1 plans. Without these fixes, `auth:sanctum` middleware returns 401 for valid tokens, Lighthouse `@guard` falls back to session auth, and the flat 60/min rate limiter lacks the read/write split required by API-05.

**Output:** Published `config/sanctum.php`, `config/auth.php` with sanctum guard, `config/lighthouse.php` with sanctum guard, named rate limiters registered, `spatie/laravel-query-builder` and `knuckleswtf/scribe` installed.

## Tasks

<task id="T1" wave="0">
  <title>Fix Sanctum + Lighthouse Configuration</title>
  <read_first>
    - config/auth.php
    - config/lighthouse.php
  </read_first>
  <action>
**Step 1 — Publish Sanctum config (config/sanctum.php does not exist yet):**
```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```
This creates `config/sanctum.php`. After publishing, open it and set the `expiration` key to `30` (minutes):
```php
'expiration' => 30,
```
Leave all other defaults unchanged.

**Step 2 — Add sanctum guard to config/auth.php:**
In the `'guards'` array, add the `sanctum` entry so it reads:
```php
'guards' => [
    'web' => [
        'driver' => 'session',
        'provider' => 'users',
    ],
    'sanctum' => [
        'driver' => 'sanctum',
        'provider' => 'users',
    ],
],
```

**Step 3 — Fix Lighthouse guard in config/lighthouse.php:**
Find the line `'guards' => null,` (around line 66) and change it to:
```php
'guards' => ['sanctum'],
```
  </action>
  <acceptance_criteria>
  - `config/sanctum.php` exists and contains `'expiration' => 30`
  - `config/auth.php` contains `'driver' => 'sanctum'`
  - `config/auth.php` contains `'provider' => 'users'` inside the sanctum guard block
  - `config/lighthouse.php` contains `'guards' => ['sanctum']` (not `null`)
  - `php artisan config:clear` exits 0
  </acceptance_criteria>
</task>

<task id="T2" wave="0">
  <title>Install Packages + Register Named Rate Limiters</title>
  <read_first>
    - app/Providers/AppServiceProvider.php
    - app/Http/Middleware/ApiRateLimitMiddleware.php
  </read_first>
  <action>
**Step 1 — Install packages:**
```bash
composer require spatie/laravel-query-builder
composer require --dev knuckleswtf/scribe
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider"
```
This creates `config/scribe.php` (configured in Plan 6) and Scribe assets.

**Step 2 — Register named rate limiters in AppServiceProvider::boot():**
Add to the top `use` imports in `app/Providers/AppServiceProvider.php`:
```php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
```

Add at the START of the `boot()` method (before observer registrations):
```php
RateLimiter::for('api-read', function (Request $request) {
    return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('api-write', function (Request $request) {
    return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
});
```

**Step 3 — Remove the old middleware alias** from AppServiceProvider::boot():
Delete this line from the `boot()` method (it will no longer be used):
```php
$this->app['router']->aliasMiddleware('api_rate_limit', \App\Http\Middleware\ApiRateLimitMiddleware::class);
```
The `ApiRateLimitMiddleware` class file can stay but is no longer registered.
  </action>
  <acceptance_criteria>
  - `composer show spatie/laravel-query-builder` exits 0 and shows version ^6
  - `composer show knuckleswtf/scribe` exits 0
  - `config/scribe.php` exists
  - `app/Providers/AppServiceProvider.php` contains `RateLimiter::for('api-read'`
  - `app/Providers/AppServiceProvider.php` contains `RateLimiter::for('api-write'`
  - `app/Providers/AppServiceProvider.php` contains `Limit::perMinute(100)`
  - `app/Providers/AppServiceProvider.php` contains `Limit::perMinute(20)`
  - `app/Providers/AppServiceProvider.php` does NOT contain `aliasMiddleware('api_rate_limit'`
  - `php artisan config:clear && php artisan route:clear` exits 0
  </acceptance_criteria>
</task>

## Verification

```bash
php artisan config:clear
php artisan route:clear
php artisan config:show auth | grep sanctum
php artisan config:show sanctum | grep expiration
composer show spatie/laravel-query-builder
composer show knuckleswtf/scribe
```

Expected: sanctum guard visible in auth config, expiration=30, both packages listed.

## Success Criteria

- `config/auth.php` declares sanctum guard with `'driver' => 'sanctum'`
- `config/sanctum.php` exists with `'expiration' => 30`
- `config/lighthouse.php` has `'guards' => ['sanctum']` (not null)
- `spatie/laravel-query-builder` installed (not dev-only)
- `knuckleswtf/scribe` installed
- Named rate limiters `api-read` (100/min) and `api-write` (20/min) registered in AppServiceProvider
- No PHP parse errors: `php artisan inspire` exits 0

## Output

After completion, create `.planning/phases/06-rest-graphql-api/06-1-SUMMARY.md` with what was done, any version numbers installed, and any unexpected findings.
