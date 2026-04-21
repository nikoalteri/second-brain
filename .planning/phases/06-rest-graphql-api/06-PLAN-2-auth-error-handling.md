---
plan: 2
phase: 6
title: "Authentication Endpoints + Error Handling + API Route Structure"
wave: 1
depends_on: [1]
requirements: [API-01, API-02, API-03, API-14]
files_modified:
  - app/Http/Controllers/Api/V1/AuthController.php
  - app/Http/Requests/Api/LoginRequest.php
  - app/Http/Requests/Api/RefreshRequest.php
  - routes/api.php
  - app/Exceptions/Handler.php
autonomous: true

must_haves:
  truths:
    - "POST /api/v1/auth/login with valid credentials returns access_token (30-min) + refresh_token (7-day)"
    - "POST /api/v1/auth/login with invalid credentials returns 401 JSON"
    - "POST /api/v1/auth/refresh with valid Bearer refresh token returns new access_token"
    - "POST /api/v1/auth/logout with valid Bearer token deletes all tokens; subsequent requests return 401"
    - "Validation errors return 422 JSON with field-level errors map"
    - "Accessing /api/v1/accounts without token returns 401 JSON (not HTML)"
    - "Accessing another user's resource returns 403 JSON"
    - "Missing resource returns 404 JSON"
  artifacts:
    - path: "app/Http/Controllers/Api/V1/AuthController.php"
      provides: "login(), refresh(), logout() methods"
      exports: ["login", "refresh", "logout"]
    - path: "app/Http/Requests/Api/LoginRequest.php"
      provides: "email + password validation"
      contains: "'email', 'required'"
    - path: "app/Http/Requests/Api/RefreshRequest.php"
      provides: "Authorization header validation"
      contains: "RefreshRequest"
    - path: "routes/api.php"
      provides: "v1 route structure with throttle:api-read / throttle:api-write groups"
      contains: "prefix('v1')"
    - path: "app/Exceptions/Handler.php"
      provides: "JSON error responses for API routes (401, 403, 404, 422)"
      contains: "api/*"
  key_links:
    - from: "routes/api.php"
      to: "app/Http/Controllers/Api/V1/AuthController.php"
      via: "Route::post('/auth/login')"
      pattern: "AuthController.*login"
    - from: "app/Exceptions/Handler.php"
      to: "routes/api.php"
      via: "request->is('api/*') check"
      pattern: "is\\('api/\\*'\\)"
---

## Objective

Create the three authentication endpoints (login, refresh, logout) using Sanctum token issuance, define the full versioned route structure for all future resource controllers, and configure the exception handler to return consistent JSON error responses for all API routes.

**Purpose:** Mobile clients need a login → token → use flow. Without the route structure, Wave 1 Plans 3+4 cannot register their controllers. Without the exception handler fix, validation errors return HTML not JSON.

**Output:** `AuthController` with 3 endpoints, `LoginRequest`, `RefreshRequest`, complete `routes/api.php` v1 skeleton with throttle groups, and updated `Handler.php` for JSON errors.

## Tasks

<task id="T1" wave="1">
  <title>Create AuthController, Form Requests, and API Route Structure</title>
  <read_first>
    - routes/api.php
    - config/sanctum.php
    - app/Models/User.php
  </read_first>
  <action>
**Step 1 — Create directory structure:**
```bash
mkdir -p app/Http/Controllers/Api/V1
mkdir -p app/Http/Requests/Api
mkdir -p app/Http/Resources/Api
```

**Step 2 — Create `app/Http/Requests/Api/LoginRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
```

**Step 3 — Create `app/Http/Requests/Api/RefreshRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RefreshRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
```

**Step 4 — Create `app/Http/Controllers/Api/V1/AuthController.php`:**
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Authenticate user and issue access + refresh tokens.
     *
     * @group Authentication
     * @unauthenticated
     * @bodyParam email string required User email. Example: user@example.com
     * @bodyParam password string required User password (min 8 chars). Example: secret1234
     * @response 200 {"access_token":"1|...","refresh_token":"2|...","token_type":"Bearer","expires_in":1800}
     * @response 401 {"message":"Invalid credentials."}
     * @response 422 {"message":"Validation failed.","errors":{"email":["The email field is required."]}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        /** @var \App\Models\User $user */
        $user = Auth::user();
        $user->tokens()->delete();

        $access  = $user->createToken('access', ['*'], now()->addMinutes(30));
        $refresh = $user->createToken('refresh', ['refresh'], now()->addDays(7));

        return response()->json([
            'access_token'  => $access->plainTextToken,
            'refresh_token' => $refresh->plainTextToken,
            'token_type'    => 'Bearer',
            'expires_in'    => 1800,
        ]);
    }

    /**
     * Refresh an expired access token using a valid refresh token.
     *
     * @group Authentication
     * @authenticated
     * @response 200 {"access_token":"3|...","token_type":"Bearer","expires_in":1800}
     * @response 401 {"message":"Unauthenticated."}
     */
    public function refresh(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Revoke only access tokens; keep the refresh token alive
        $user->tokens()->where('name', 'access')->delete();

        $access = $user->createToken('access', ['*'], now()->addMinutes(30));

        return response()->json([
            'access_token' => $access->plainTextToken,
            'token_type'   => 'Bearer',
            'expires_in'   => 1800,
        ]);
    }

    /**
     * Logout user and invalidate all tokens.
     *
     * @group Authentication
     * @authenticated
     * @response 200 {"message":"Logged out."}
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
```

**Step 5 — Rewrite `routes/api.php` completely:**
```php
<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CreditCardController;
use App\Http\Controllers\Api\V1\LoanController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\TransactionController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // ─── Authentication (no auth guard required) ───────────────────────────
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
    });

    // ─── Read endpoints — 100 req/min ──────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:api-read'])->group(function () {
        Route::get('accounts', [AccountController::class, 'index']);
        Route::get('accounts/{account}', [AccountController::class, 'show']);

        Route::get('transactions', [TransactionController::class, 'index']);
        Route::get('transactions/{transaction}', [TransactionController::class, 'show']);

        Route::get('loans', [LoanController::class, 'index']);
        Route::get('loans/{loan}', [LoanController::class, 'show']);

        Route::get('credit-cards', [CreditCardController::class, 'index']);
        Route::get('credit-cards/{creditCard}', [CreditCardController::class, 'show']);

        Route::get('subscriptions', [SubscriptionController::class, 'index']);
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show']);
    });

    // ─── Write endpoints — 20 req/min ─────────────────────────────────────
    Route::middleware(['auth:sanctum', 'throttle:api-write'])->group(function () {
        Route::post('accounts', [AccountController::class, 'store']);
        Route::put('accounts/{account}', [AccountController::class, 'update']);
        Route::patch('accounts/{account}', [AccountController::class, 'update']);
        Route::delete('accounts/{account}', [AccountController::class, 'destroy']);

        Route::post('transactions', [TransactionController::class, 'store']);
        Route::put('transactions/{transaction}', [TransactionController::class, 'update']);
        Route::patch('transactions/{transaction}', [TransactionController::class, 'update']);
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy']);

        Route::post('loans', [LoanController::class, 'store']);
        Route::put('loans/{loan}', [LoanController::class, 'update']);
        Route::patch('loans/{loan}', [LoanController::class, 'update']);
        Route::delete('loans/{loan}', [LoanController::class, 'destroy']);

        Route::post('credit-cards', [CreditCardController::class, 'store']);
        Route::put('credit-cards/{creditCard}', [CreditCardController::class, 'update']);
        Route::patch('credit-cards/{creditCard}', [CreditCardController::class, 'update']);
        Route::delete('credit-cards/{creditCard}', [CreditCardController::class, 'destroy']);

        Route::post('subscriptions', [SubscriptionController::class, 'store']);
        Route::put('subscriptions/{subscription}', [SubscriptionController::class, 'update']);
        Route::patch('subscriptions/{subscription}', [SubscriptionController::class, 'update']);
        Route::delete('subscriptions/{subscription}', [SubscriptionController::class, 'destroy']);
    });
});
```
  </action>
  <acceptance_criteria>
  - `app/Http/Controllers/Api/V1/AuthController.php` exists and contains `public function login(`
  - `app/Http/Controllers/Api/V1/AuthController.php` contains `public function refresh(`
  - `app/Http/Controllers/Api/V1/AuthController.php` contains `public function logout(`
  - `app/Http/Controllers/Api/V1/AuthController.php` contains `$user->createToken('access', ['*'], now()->addMinutes(30))`
  - `app/Http/Controllers/Api/V1/AuthController.php` contains `$user->createToken('refresh', ['refresh'], now()->addDays(7))`
  - `app/Http/Requests/Api/LoginRequest.php` exists and contains `'email'` and `'password'`
  - `routes/api.php` contains `prefix('v1')`
  - `routes/api.php` contains `throttle:api-read`
  - `routes/api.php` contains `throttle:api-write`
  - `routes/api.php` contains `AuthController::class, 'login'`
  - `php artisan route:list --path=api/v1` exits 0 and shows login, refresh, logout routes
  </acceptance_criteria>
</task>

<task id="T2" wave="1">
  <title>Update Exception Handler for JSON API Error Responses</title>
  <read_first>
    - app/Exceptions/Handler.php
  </read_first>
  <action>
**Goal:** All API routes (`api/*`) must return JSON for 401, 403, 404, 422 errors — never HTML.

Open `app/Exceptions/Handler.php`. Laravel 12 uses the new `bootstrap/app.php` exception handler registration. Check if `Handler.php` already exists with a `render()` method or if exceptions are registered in `bootstrap/app.php`.

**If `app/Exceptions/Handler.php` exists with a `render()` method**, add the following block at the start of the `render()` method BEFORE the `parent::render()` call:

```php
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Throwable;

public function render($request, Throwable $e): \Symfony\Component\HttpFoundation\Response
{
    if ($request->is('api/*') || $request->expectsJson()) {
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        if ($e instanceof ModelNotFoundException) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        if ($e instanceof AuthorizationException) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($e instanceof AuthenticationException) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
    }

    return parent::render($request, $e);
}
```

**If exceptions are handled via `bootstrap/app.php`** (Laravel 12 style), add to `bootstrap/app.php` inside `->withExceptions()`:

```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (ValidationException $e, Request $request) {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json([
                'message' => 'Validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }
    });

    $exceptions->render(function (ModelNotFoundException $e, Request $request) {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }
    });

    $exceptions->render(function (AuthorizationException $e, Request $request) {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
    });

    $exceptions->render(function (AuthenticationException $e, Request $request) {
        if ($request->is('api/*') || $request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }
    });
})
```

Read `bootstrap/app.php` first to determine which pattern applies, then implement the correct one.
  </action>
  <acceptance_criteria>
  - The file containing exception rendering (either `app/Exceptions/Handler.php` or `bootstrap/app.php`) contains `request->is('api/*')`
  - The file contains `ValidationException` handler returning 422 with `'errors'` key
  - The file contains `ModelNotFoundException` handler returning 404 with `'message'` key
  - The file contains `AuthorizationException` handler returning 403
  - The file contains `AuthenticationException` handler returning 401
  - `php artisan config:clear` exits 0
  - `php artisan route:list --path=api` shows at least 3 auth routes (login, refresh, logout)
  </acceptance_criteria>
</task>

## Verification

```bash
php artisan route:clear && php artisan route:list --path=api/v1
# Should list: POST api/v1/auth/login, POST api/v1/auth/refresh, POST api/v1/auth/logout
# Plus GET/POST/PUT/PATCH/DELETE for accounts, transactions, loans, credit-cards, subscriptions

php artisan test --filter=AuthApiTest
# Will fail if tests don't exist yet — that's expected; check for class resolution errors only
```

## Success Criteria

- `POST /api/v1/auth/login` with valid creds returns 200 JSON with `access_token`, `refresh_token`, `token_type`, `expires_in`
- `POST /api/v1/auth/login` with invalid creds returns 401 JSON `{"message": "Invalid credentials."}`
- `POST /api/v1/auth/logout` with valid Bearer token returns 200 `{"message": "Logged out."}`
- `GET /api/v1/accounts` without token returns 401 JSON (not HTML 302)
- Validation errors on login return 422 with `errors.email` / `errors.password` keys

## Output

After completion, create `.planning/phases/06-rest-graphql-api/06-2-SUMMARY.md`.
