---
plan: 6
phase: 6
title: "Scribe API Documentation — OpenAPI 3.0 at /api/docs"
wave: 2
depends_on: [1, 2, 3, 4]
requirements: [API-19]
files_modified:
  - config/scribe.php
  - app/Http/Controllers/Api/V1/AuthController.php
  - app/Http/Controllers/Api/V1/AccountController.php
  - app/Http/Controllers/Api/V1/TransactionController.php
  - app/Http/Controllers/Api/V1/LoanController.php
  - app/Http/Controllers/Api/V1/CreditCardController.php
  - app/Http/Controllers/Api/V1/SubscriptionController.php
  - public/docs/
autonomous: true

must_haves:
  truths:
    - "GET /docs renders Swagger UI HTML page in the browser"
    - "GET /docs.json returns valid OpenAPI 3.0 JSON spec"
    - "All 5 resource groups (Accounts, Transactions, Loans, Credit Cards, Subscriptions) appear in the docs"
    - "Authentication endpoints (login, refresh, logout) appear in the docs"
    - "All endpoints show the Bearer auth requirement"
    - "Scribe does NOT document Filament admin routes (only api/v1/* is scanned)"
  artifacts:
    - path: "config/scribe.php"
      provides: "Scribe configured to scan only api/v1/* routes"
      contains: "api/v1/*"
    - path: "public/docs/index.html"
      provides: "Swagger UI HTML page"
      contains: "swagger"
    - path: "public/docs/openapi.yaml"
      provides: "OpenAPI 3.0 YAML spec"
      contains: "openapi: 3"
  key_links:
    - from: "config/scribe.php"
      to: "routes/api.php"
      via: "prefix match api/v1/*"
      pattern: "api/v1/\\*"
    - from: "app/Http/Controllers/Api/V1/AccountController.php"
      to: "public/docs/openapi.yaml"
      via: "@group Accounts docblock"
      pattern: "@group"
---

## Objective

Configure `knuckleswtf/scribe` to scan only the `api/v1/*` routes, ensure all 6 controllers have proper `@group` docblocks, run `php artisan scribe:generate`, and verify the resulting Swagger UI is accessible at `/docs`.

**Purpose:** Delivers API-19 (OpenAPI 3.0 spec + Swagger UI at `/api/docs`). Scribe was installed in Plan 1 and the config was published — this plan configures and runs it.

**Output:** `config/scribe.php` configured, controller docblocks finalized, `public/docs/` folder generated with `index.html` and `openapi.yaml`.

## Tasks

<task id="T1" wave="2">
  <title>Configure config/scribe.php to Restrict Docs to api/v1/*</title>
  <read_first>
    - config/scribe.php
    - routes/api.php
  </read_first>
  <action>
Read `config/scribe.php` fully. It was published by Scribe's service provider in Plan 1. Update the following keys:

**1. Set `type` to `laravel` (serves docs via Laravel route, not static):**
```php
'type' => 'laravel',
```

**2. Set `base_url` to your local dev URL:**
```php
'base_url' => env('APP_URL', 'http://localhost:8000'),
```

**3. Configure routes to ONLY scan api/v1/* (prevents Filament admin routes from appearing):**
```php
'routes' => [
    [
        'match' => [
            'prefixes'  => ['api/v1/*'],
            'domains'   => ['*'],
            'versions'  => ['v1'],
        ],
        'apply' => [
            'headers' => [
                'Accept'        => 'application/json',
                'Authorization' => 'Bearer {token}',
            ],
            'response_calls' => [
                'methods' => [],  // Disable actual HTTP calls — use docblock examples only
            ],
        ],
    ],
],
```

**4. Configure auth:**
```php
'auth' => [
    'enabled'   => true,
    'default'   => true,
    'in'        => 'bearer',
    'name'      => 'Authorization',
    'use_value' => env('SCRIBE_AUTH_KEY', 'test-token-here'),
    'placeholder' => '{ACCESS_TOKEN}',
    'extra_info' => 'Obtain a token via POST /api/v1/auth/login. Access tokens expire in 30 minutes.',
],
```

**5. Set title and description:**
```php
'title' => 'Fluxa Finance API',
'description' => 'REST API for the Fluxa personal finance tracker. All endpoints require Bearer token authentication obtained via POST /api/v1/auth/login.',
'logo' => false,
```

**6. Enable OpenAPI (it is enabled by default, but confirm):**
```php
'openapi' => [
    'enabled' => true,
    'overrides' => [],
],
```

**7. Configure output path:**
```php
'output_path' => 'public/docs',
```

Leave all other defaults unchanged. Save `config/scribe.php`.
  </action>
  <acceptance_criteria>
  - `config/scribe.php` contains `'type' => 'laravel'`
  - `config/scribe.php` contains `'prefixes' => ['api/v1/*']`
  - `config/scribe.php` contains `'enabled' => true` under `auth`
  - `config/scribe.php` contains `'in' => 'bearer'`
  - `config/scribe.php` contains `'output_path' => 'public/docs'`
  - `config/scribe.php` does NOT contain a route rule matching `admin/*` or `filament/*`
  - `php artisan config:clear` exits 0
  </acceptance_criteria>
</task>

<task id="T2" wave="2">
  <title>Generate OpenAPI Docs and Verify /docs Endpoint</title>
  <read_first>
    - config/scribe.php
    - app/Http/Controllers/Api/V1/AuthController.php
    - app/Http/Controllers/Api/V1/AccountController.php
    - app/Http/Controllers/Api/V1/TransactionController.php
    - app/Http/Controllers/Api/V1/LoanController.php
    - app/Http/Controllers/Api/V1/CreditCardController.php
    - app/Http/Controllers/Api/V1/SubscriptionController.php
  </read_first>
  <action>
**Step 1 — Verify all controllers have @group docblocks:**

Each controller's class-level or method-level docblocks must have `@group` annotations matching these group names:
- `AuthController` → `@group Authentication`
- `AccountController` → `@group Accounts`
- `TransactionController` → `@group Transactions`
- `LoanController` → `@group Loans`
- `CreditCardController` → `@group Credit Cards`
- `SubscriptionController` → `@group Subscriptions`

All index() methods should have `@queryParam` annotations. All store() methods should reference the request class for body params (Scribe auto-reads them). Controllers already have these from Plans 3+4 docblocks — verify they are present. If any are missing, add a class-level docblock with `@group`:

```php
/**
 * @group Subscriptions
 * 
 * Endpoints for managing recurring subscriptions.
 */
class SubscriptionController extends Controller
```

**Step 2 — Run Scribe generation:**
```bash
php artisan scribe:generate
```

If Scribe prompts for confirmation, respond yes. Fix any errors that appear:

Common errors and fixes:
- `Route [api/v1/...] uses controller [...] not found` → The controller class doesn't exist or has wrong namespace — verify each controller class exists
- `Unable to extract response from route` → Add `@response` docblocks (already added in Plan 2 for auth endpoints) or disable response_calls
- `Unknown type hint` → Ensure all `use` imports are correct in each controller

**Step 3 — Verify output files exist:**
```bash
ls public/docs/
# Should show: index.html, openapi.yaml (or openapi.json), and possibly collection.json
```

**Step 4 — Start dev server and verify /docs:**
```bash
php artisan serve &
sleep 2
curl -s http://localhost:8000/docs | grep -i swagger
# Expected: Returns HTML containing "swagger" or "Swagger UI"

curl -s http://localhost:8000/docs.json | jq '.info.title'
# Expected: "Fluxa Finance API"
```

**Step 5 — Verify Filament routes are excluded:**
```bash
cat public/docs/openapi.yaml | grep -i "filament\|admin" | head -5
# Expected: No filament or admin paths in the spec
```

**Step 6 — Commit the generated docs:**
The `public/docs/` directory should be committed to git (it's static). If `.gitignore` excludes `public/docs/`, remove that exclusion.
  </action>
  <acceptance_criteria>
  - `php artisan scribe:generate` exits 0
  - `public/docs/index.html` exists and contains "swagger" (case-insensitive)
  - `public/docs/openapi.yaml` exists and contains `openapi: 3`
  - `public/docs/openapi.yaml` contains `Fluxa Finance API` or the configured title
  - `public/docs/openapi.yaml` contains paths for `/api/v1/accounts`, `/api/v1/transactions`, `/api/v1/loans`
  - `public/docs/openapi.yaml` does NOT contain paths for `/admin` or `/filament`
  - `public/docs/openapi.yaml` contains bearer security scheme (`type: http`, `scheme: bearer`)
  - GET `/docs` returns 200 HTML (via Laravel route registered by Scribe)
  </acceptance_criteria>
</task>

## Verification

```bash
# Check generated files
ls -la public/docs/
cat public/docs/openapi.yaml | head -30

# Count documented endpoints
grep -c "operationId" public/docs/openapi.yaml

# Check auth section
grep -A5 "securitySchemes" public/docs/openapi.yaml

# Ensure Filament not leaked
grep -i "admin\|filament" public/docs/openapi.yaml | wc -l
# Expected: 0
```

## Success Criteria

- `GET /docs` returns Swagger UI HTML
- `GET /docs.json` returns OpenAPI 3.0 JSON
- At least 25 documented operations (5 resources × 5 CRUD + 3 auth = 28)
- All operations require Bearer auth (shown in Swagger UI padlock)
- Filament/admin routes do NOT appear in the spec
- `openapi.yaml` is valid OpenAPI 3.0 (no Scribe warnings)

## Output

After completion, create `.planning/phases/06-rest-graphql-api/06-6-SUMMARY.md`.
