# Phase 6: REST + GraphQL API — Research

**Researched:** 2026-04-21  
**Domain:** Laravel REST API, Lighthouse GraphQL, Sanctum, API Documentation  
**Confidence:** HIGH (all critical findings verified against installed packages and codebase)

---

<phase_requirements>
## Phase Requirements

| ID | Description | Research Support |
|----|-------------|------------------|
| API-01 | User authenticates via email/password → JWT access token (30 min) + refresh token (7 days) | Sanctum 4.3.1 installed; use `createToken()` with `expiration` config + manual refresh endpoint |
| API-02 | User refreshes an expired access token without re-authenticating | Implement `POST /api/v1/auth/refresh` that revokes current token and issues a new one |
| API-03 | User can logout and invalidate all tokens | `$user->tokens()->delete()` via Sanctum |
| API-04 | API enforces user_id scoping; user cannot access another user's data | Existing policies + HasUserScoping trait; inconsistent across models — see Pitfall 3 |
| API-05 | Rate limiting: 100 read/min, 20 write/min; 429 on excess | Custom `ApiRateLimitMiddleware` exists (60/min flat); upgrade to named rate limiters |
| API-06 | CRUD on Accounts via REST `/api/v1/accounts` | Account model + AccountPolicy already exist |
| API-07 | CRUD on Transactions via REST `/api/v1/transactions` with filtering | Transaction model + TransactionPolicy exist; add `Spatie\QueryBuilder` for filters |
| API-08 | CRUD on Loans `/api/v1/loans` with nested payment access | Loan + LoanPayment models + policies exist |
| API-09 | CRUD on Credit Cards `/api/v1/credit-cards` with cycle/expense access | CreditCard + CreditCardCycle/Payment/Expense models + policies exist |
| API-10 | CRUD on Subscriptions `/api/v1/subscriptions` | Subscription model + SubscriptionPolicy exist |
| API-11 | Cursor-based pagination, default 20 items | Laravel `cursorPaginate(20)` |
| API-12 | Sort by any indexed column via query param | `spatie/laravel-query-builder` `allowedSorts()` |
| API-13 | Filter by status, category, date range with logical operators | `spatie/laravel-query-builder` `allowedFilters()` |
| API-14 | Consistent error responses 400/422/403/404 | Laravel's JSON exception handler + custom `Handler.php` |
| API-15 | GraphQL: core finance types with nested rels (no N+1) | Lighthouse `@with` directive + `BatchLoader` |
| API-16 | GraphQL: mutations for CRUD with input validation | Lighthouse `@create`, `@update`, `@delete` directives + `@rules` |
| API-17 | GraphQL: aggregated queries (totals by category, monthly cashflow) | Custom Lighthouse resolvers in `app/GraphQL/Queries/` |
| API-18 | GraphQL schema fully documented; introspection enabled | `@deprecated`, description strings in .graphql; Lighthouse default enables introspection |
| API-19 | OpenAPI 3.0 spec at `/api/docs` via Swagger UI | `knuckleswtf/scribe` ^4.x |
| API-20 | All paginated endpoints < 500ms; eager loading verified | `with()` on every collection query; `@with` on GraphQL fields |
</phase_requirements>

---

## Summary

Fluxa already has the core infrastructure needed for Phase 6: **Sanctum 4.3.1** is installed for API token auth, **Lighthouse 6.66.0** is installed and configured for GraphQL, and a custom `ApiRateLimitMiddleware` exists. The `graphql/schema.graphql` only contains the User type — it needs full extension for all finance entities. All 11 resource policies are registered and working.

The primary work is: (1) stand up Sanctum token auth with expiry/refresh, (2) build REST controllers for 5 resources under `/api/v1/`, (3) expand the GraphQL schema with finance types and mutations, (4) upgrade rate limiting to differentiate reads vs writes, and (5) add `knuckleswtf/scribe` for OpenAPI 3.0 docs.

**Primary recommendation:** Use Sanctum (already installed) for token auth — no new JWT package needed. Use `spatie/laravel-query-builder` for REST filtering/sorting. Use Scribe for docs.

---

## Standard Stack

### Core (Already Installed)
| Library | Installed Version | Purpose | Status |
|---------|------------------|---------|--------|
| `laravel/sanctum` | v4.3.1 | API token authentication | ✅ Installed |
| `nuwave/lighthouse` | v6.66.0 | GraphQL server | ✅ Installed |
| `spatie/laravel-permission` | ^7.2 | RBAC / role checks | ✅ Installed |

### To Install
| Library | Version | Purpose | Why |
|---------|---------|---------|-----|
| `spatie/laravel-query-builder` | ^6.x | REST filtering, sorting, pagination | First-class Laravel query builder with allowlist-based filters, used by thousands of Laravel APIs |
| `knuckleswtf/scribe` | ^4.x | OpenAPI 3.0 auto-docs | Reads Laravel routes + docblocks; generates Swagger UI at `/api/docs`; better auto-discovery than L5-Swagger |

### Alternatives Considered
| Instead of | Could Use | Tradeoff |
|------------|-----------|----------|
| Sanctum tokens | `php-open-source-saver/jwt-auth` | True stateless JWT, but adds package + complexity; Sanctum already installed and covers the use case |
| Scribe | `darkaonline/l5-swagger` | L5-Swagger needs verbose `@OA\` annotations on every controller method; Scribe auto-discovers from code |
| `spatie/laravel-query-builder` | Manual filter parsing | Custom filters are fragile and miss edge cases (type casting, relation filters, XSS) |

**Installation:**
```bash
composer require spatie/laravel-query-builder knuckleswtf/scribe --dev
php artisan vendor:publish --provider="Knuckles\Scribe\ScribeServiceProvider"
```

---

## Architecture Patterns

### Recommended Project Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           ├── AuthController.php        # login, refresh, logout
│   │           ├── AccountController.php
│   │           ├── TransactionController.php
│   │           ├── LoanController.php
│   │           ├── CreditCardController.php
│   │           └── SubscriptionController.php
│   ├── Requests/
│   │   └── Api/
│   │       ├── StoreAccountRequest.php
│   │       ├── UpdateAccountRequest.php
│   │       └── ...                           # one per resource×action
│   └── Resources/
│       └── Api/
│           ├── AccountResource.php           # JSON:API-style transformers
│           ├── TransactionResource.php
│           └── ...
├── GraphQL/
│   ├── Queries/
│   │   └── MonthlyCashflowQuery.php          # custom resolvers for API-17
│   └── Mutations/
│       └── (auto-resolved via Lighthouse directives)
routes/
└── api.php                                   # versioned under /api/v1/
graphql/
└── schema.graphql                            # expanded with all finance types
```

### Pattern 1: REST API Versioning + Route Structure

**What:** Group all routes under `/api/v1/` prefix with Sanctum guard middleware.  
**When to use:** Always — future-proofs API without breaking mobile clients.

```php
// routes/api.php
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api-read'])->group(function () {
    // Auth (no auth guard on login/refresh)
    Route::post('/auth/login', [AuthController::class, 'login'])->withoutMiddleware(['auth:sanctum']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh'])->withoutMiddleware(['auth:sanctum']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Resources
    Route::apiResource('accounts', AccountController::class);
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('loans', LoanController::class);
    Route::apiResource('loans.payments', LoanPaymentController::class)->shallow();
    Route::apiResource('credit-cards', CreditCardController::class);
    Route::apiResource('credit-cards.expenses', CreditCardExpenseController::class)->shallow();
    Route::apiResource('subscriptions', SubscriptionController::class);
});

// Write endpoints use separate throttle
Route::prefix('v1')->middleware(['auth:sanctum', 'throttle:api-write'])->group(function () {
    // POST/PUT/PATCH/DELETE matched via route registration above; split at middleware level via named limiters
});
```

**Note on read vs write throttle:** The cleaner Laravel approach for differentiating read/write limits is to use named rate limiters registered in `RouteServiceProvider::configureRateLimiting()` and apply the correct one per route verb, or use a single enhanced `ApiRateLimitMiddleware` that detects HTTP verb.

### Pattern 2: Sanctum Token Auth + Expiry + Refresh

**What:** Sanctum `createToken()` with configured expiration. A refresh endpoint revokes the current token and issues a new one.  
**When to use:** All API auth flows (API-01, API-02, API-03).

```php
// config/sanctum.php — add expiry
'expiration' => 30, // minutes for access token

// AuthController.php
public function login(LoginRequest $request): JsonResponse
{
    if (!Auth::attempt($request->only('email', 'password'))) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    $user = Auth::user();
    // Revoke any existing tokens
    $user->tokens()->delete();

    $accessToken = $user->createToken('access', ['*'], now()->addMinutes(30));
    $refreshToken = $user->createToken('refresh', ['refresh'], now()->addDays(7));

    return response()->json([
        'access_token'  => $accessToken->plainTextToken,
        'refresh_token' => $refreshToken->plainTextToken,
        'token_type'    => 'Bearer',
        'expires_in'    => 1800,
    ]);
}

public function refresh(RefreshRequest $request): JsonResponse
{
    // Validate refresh token, revoke it, issue new access token
    $user = $request->user('sanctum'); // authenticate via refresh token
    $user->tokens()->where('name', 'access')->delete();
    $newToken = $user->createToken('access', ['*'], now()->addMinutes(30));

    return response()->json([
        'access_token' => $newToken->plainTextToken,
        'token_type'   => 'Bearer',
        'expires_in'   => 1800,
    ]);
}

public function logout(Request $request): JsonResponse
{
    $request->user()->tokens()->delete(); // API-03: invalidate all
    return response()->json(['message' => 'Logged out']);
}
```

**Sanctum guard in `config/auth.php`:** Add `sanctum` guard:
```php
'guards' => [
    'web'    => ['driver' => 'session', 'provider' => 'users'],
    'sanctum' => ['driver' => 'sanctum', 'provider' => 'users'], // add this
],
```
Actually: Sanctum auto-registers its guard. Use `auth:sanctum` middleware directly.

### Pattern 3: REST Controller with Policy + User Scoping

**What:** API Resource Controller that authorizes via existing policies and scopes queries to the authenticated user.  
**When to use:** All 5 finance resource controllers.

```php
// app/Http/Controllers/Api/V1/AccountController.php
class AccountController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = QueryBuilder::for(Account::class)
            ->where('user_id', $request->user()->id)   // explicit scope (model lacks HasUserScoping)
            ->allowedFilters(['type', 'is_active', 'currency'])
            ->allowedSorts(['name', 'balance', 'created_at'])
            ->cursorPaginate(20);

        return AccountResource::collection($accounts);
    }

    public function store(StoreAccountRequest $request): AccountResource
    {
        $this->authorize('create', Account::class);
        $account = Account::create([...$request->validated(), 'user_id' => $request->user()->id]);
        return new AccountResource($account);
    }

    public function show(Request $request, Account $account): AccountResource
    {
        $this->authorize('view', $account); // AccountPolicy checks user_id match
        return new AccountResource($account);
    }

    public function update(UpdateAccountRequest $request, Account $account): AccountResource
    {
        $this->authorize('update', $account);
        $account->update($request->validated());
        return new AccountResource($account);
    }

    public function destroy(Request $request, Account $account): Response
    {
        $this->authorize('delete', $account);
        $account->delete();
        return response()->noContent();
    }
}
```

### Pattern 4: Named Rate Limiters (Read vs Write)

**What:** Replace flat 60/min with named limiters (100 read / 20 write).  
**When to use:** `App\Providers\RouteServiceProvider::configureRateLimiting()` (or `AppServiceProvider`).

```php
// In AppServiceProvider::boot() or RouteServiceProvider::configureRateLimiting()
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('api-read', function (Request $request) {
    return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('api-write', function (Request $request) {
    return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
});
```

Then in `routes/api.php`:
```php
// READ endpoints (GET)
Route::middleware(['auth:sanctum', 'throttle:api-read'])->group(function () {
    Route::get('accounts', [AccountController::class, 'index']);
    Route::get('accounts/{account}', [AccountController::class, 'show']);
    // ...
});

// WRITE endpoints (POST/PUT/PATCH/DELETE)
Route::middleware(['auth:sanctum', 'throttle:api-write'])->group(function () {
    Route::post('accounts', [AccountController::class, 'store']);
    // ...
});
```

### Pattern 5: Lighthouse GraphQL Schema Expansion

**What:** Extend `graphql/schema.graphql` with finance types, queries, and mutations.  
**When to use:** GraphQL API (API-15, API-16, API-17, API-18).

```graphql
# graphql/schema.graphql

type Query {
    # Finance queries
    accounts(
        is_active: Boolean @eq
        type: String @eq
    ): [Account!]! @paginate(defaultCount: 20) @guard

    account(id: ID! @eq): Account @find @guard @can(ability: "view", find: "id")

    transactions(
        account_id: ID @eq
        date: DateRange @whereBetween(key: "date")
    ): [Transaction!]! @paginate(defaultCount: 20) @guard @with(["account", "category"])

    loans: [Loan!]! @paginate(defaultCount: 20) @guard
    loan(id: ID! @eq): Loan @find @guard

    creditCards: [CreditCard!]! @paginate(defaultCount: 20) @guard
    subscriptions: [Subscription!]! @paginate(defaultCount: 20) @guard

    # API-17: aggregated query (custom resolver)
    monthlyCashflow(year: Int!, month: Int!): MonthlyCashflow! @guard
}

type Mutation {
    createAccount(input: CreateAccountInput! @spread): Account! @create @guard
    updateAccount(id: ID!, input: UpdateAccountInput! @spread): Account! @update @guard
    deleteAccount(id: ID!): Account @delete @guard

    createTransaction(input: CreateTransactionInput! @spread): Transaction! @create @guard
    updateTransaction(id: ID!, input: UpdateTransactionInput! @spread): Transaction! @update @guard
    deleteTransaction(id: ID!): Transaction @delete @guard

    # Similar patterns for Loan, CreditCard, Subscription
}

type Account {
    id: ID!
    name: String!
    type: String!
    balance: Float!
    opening_balance: Float!
    currency: String!
    is_active: Boolean!
    transactions: [Transaction!]! @hasMany @with(["account"])
    created_at: DateTime!
    updated_at: DateTime!
}

type Transaction {
    id: ID!
    amount: Float!
    date: Date!
    description: String
    is_transfer: Boolean!
    account: Account! @belongsTo
    category: TransactionCategory @belongsTo
    created_at: DateTime!
}
# ... etc for Loan, CreditCard, Subscription, MonthlyCashflow
```

**N+1 Prevention:** Use `@with` directive on collection fields and `@paginate` (Lighthouse batches queries automatically).

### Pattern 6: Scribe API Documentation Setup

**What:** Scribe generates OpenAPI 3.0 spec + Swagger UI from routes + docblocks.  
**When to use:** API-19.

```php
// config/scribe.php (after vendor:publish)
'type' => 'laravel',
'routes' => [
    [
        'match' => [
            'prefixes' => ['api/v1/*'],
            'domains' => ['*'],
        ],
        'apply' => [
            'headers' => ['Authorization' => 'Bearer {token}'],
        ],
    ],
],
'auth' => [
    'enabled' => true,
    'default' => true,
    'in' => 'bearer',
    'name' => 'Authorization',
],
'postman' => ['enabled' => true],
'openapi' => ['enabled' => true],
```

Generate docs:
```bash
php artisan scribe:generate
# Serves at /api/docs (Swagger UI) and /api/docs.json (OpenAPI spec)
```

Controller docblocks:
```php
/**
 * List all accounts for the authenticated user.
 *
 * @group Accounts
 * @queryParam filter[is_active] Filter by active status. Example: true
 * @queryParam sort Sort field. Example: -balance
 * @queryParam cursor Cursor for pagination.
 */
public function index(Request $request): AnonymousResourceCollection
```

### Anti-Patterns to Avoid

- **Forgetting user_id scoping on API queries:** Not all models use `HasUserScoping`. `Account`, `Transaction`, `Loan`, `CreditCard` do NOT have it. Always add `.where('user_id', auth()->id())` or `authorize()` in controllers.
- **Using `Route::resource()` instead of `Route::apiResource()`:** `apiResource` omits `create` and `edit` HTML form routes unnecessary for APIs.
- **Returning Eloquent models directly from controllers:** Always use `JsonResource` classes — models expose `$hidden` fields and internal casts incorrectly.
- **Disabling GraphQL introspection in development:** Keep introspection on (Lighthouse default); only disable in production if security required.
- **Using Sanctum session cookies for mobile API:** Configure `stateful` domains correctly; mobile apps MUST use Bearer token, not session cookies.

---

## Don't Hand-Roll

| Problem | Don't Build | Use Instead | Why |
|---------|-------------|-------------|-----|
| Filter/sort/field params | Custom query parser | `spatie/laravel-query-builder` | Handles type casting, XSS, relation filters, allowlists securely |
| OpenAPI docs | Manual YAML/JSON | `knuckleswtf/scribe` | Auto-discovers from routes, docblocks, request rules, response examples |
| Rate limiting with per-user keys | Custom Redis counter | Laravel named `RateLimiter` facade | Built into framework, handles burst/headers/429 responses |
| Pagination metadata | Custom `meta` arrays | `CursorPaginator` + `JsonResource::collection()` | Laravel handles `next_cursor`, `prev_cursor`, `per_page` correctly |
| GraphQL N+1 batching | Custom DataLoader | Lighthouse `@with` + `@paginate` | Lighthouse auto-eager-loads on paginated collections; `@with` handles nested relations |

**Key insight:** Laravel's ecosystem has battle-tested solutions for every API concern. Custom implementations consistently miss edge cases (pagination off-by-one, rate limit bypass via forged IP headers, filter injection attacks).

---

## Common Pitfalls

### Pitfall 1: HasUserScoping Is Only on Some Models

**What goes wrong:** Developer queries `Account::all()` or `Loan::all()` thinking a global scope will filter by user — but `Account`, `Transaction`, `Loan`, `CreditCard`, `CreditCardCycle`, `CreditCardExpense`, `CreditCardPayment` do NOT use `HasUserScoping`. Data from all users is returned.

**Why it happens:** The trait was applied selectively. `Subscription`, `TransactionCategory`, `UserSetting` have it; financial models do not.

**How to avoid:** In every API controller index method, always add explicit `->where('user_id', auth()->id())`. Verify with `$this->authorize('viewAny', ...)` which hits the policy, and then scope the query.

**Warning signs:** Tests that create 2 users and verify user B cannot list user A's data.

### Pitfall 2: Sanctum `auth:sanctum` Guard Not Configured in auth.php

**What goes wrong:** `auth:sanctum` middleware rejects valid tokens because the guard is not recognized.

**Why it happens:** Sanctum auto-registers its guard, but `config/auth.php` in this project only defines `web`. The `guards` array must include `sanctum`.

**How to avoid:** After installing Sanctum, run `php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"` and ensure `config/sanctum.php` is published. Add to `config/auth.php`:
```php
'guards' => [
    'web'     => ['driver' => 'session', 'provider' => 'users'],
    'sanctum' => ['driver' => 'sanctum', 'provider' => 'users'],
],
```

**Warning signs:** 401 errors on all protected routes even with valid Bearer token.

### Pitfall 3: Business Logic Observers Fire During API Mutations

**What goes wrong:** Creating a `Transaction` via API triggers `TransactionObserver::created()` which runs `AccountBalanceService::handleCreated()`. This is correct behavior — but API tests that don't expect it will see unexpected balance changes.

**Why it happens:** Observers are registered globally in `AppServiceProvider::boot()`. API requests go through the same lifecycle.

**How to avoid:** This is actually desired behavior (financial integrity). In tests, use `RefreshDatabase` and assert the side effects (account balance update) explicitly. Don't silence observers in tests.

**Warning signs:** API tests failing with unexpected balance assertions.

### Pitfall 4: GraphQL Schema Directive `@guard` Without Sanctum Guard Config

**What goes wrong:** `@guard` directive in Lighthouse defaults to the `guards` key in `config/lighthouse.php` (currently `null`, falling back to Laravel default which is `web`/session). Session auth doesn't work for Bearer token API clients.

**Why it happens:** `config/lighthouse.php` line: `'guards' => null` — falls back to web guard.

**How to avoid:** Update `config/lighthouse.php`:
```php
'guards' => ['sanctum'],
```
Or use the `@guard(with: ["sanctum"])` directive per field.

**Warning signs:** GraphQL requests with valid Bearer token returning `Unauthenticated`.

### Pitfall 5: Rate Limiter Headers Not Returned by Custom Middleware

**What goes wrong:** The existing `ApiRateLimitMiddleware` returns a 429 response but does not set `X-RateLimit-Limit`, `X-RateLimit-Remaining`, `Retry-After` headers. API clients can't implement backoff.

**Why it happens:** Custom middleware manually calls `$limiter->hit()` without using Laravel's built-in `ThrottleRequests` middleware which adds headers automatically.

**How to avoid:** Use Laravel's named rate limiters with `throttle:api-read` middleware alias (which uses the framework's `ThrottleRequests` middleware) instead of the custom `ApiRateLimitMiddleware`. The custom one can be deprecated.

### Pitfall 6: Scribe Documenting Filament / Admin Routes

**What goes wrong:** `php artisan scribe:generate` picks up all Laravel routes including Filament admin panel routes, polluting the docs.

**Why it happens:** Scribe default config scans all routes.

**How to avoid:** In `config/scribe.php`, restrict to `api/v1/*` prefix:
```php
'routes' => [['match' => ['prefixes' => ['api/v1/*']]]],
```

---

## Code Examples

### Sanctum Token Auth — Login Flow
```php
// Source: Laravel Sanctum 4.x official docs + codebase pattern
public function login(LoginRequest $request): JsonResponse
{
    if (!Auth::attempt($request->validated())) {
        return response()->json(['message' => 'Invalid credentials.'], 401);
    }

    /** @var User $user */
    $user = Auth::user();
    $user->tokens()->delete(); // revoke old tokens on re-login

    return response()->json([
        'access_token'  => $user->createToken('access', ['*'], now()->addMinutes(30))->plainTextToken,
        'refresh_token' => $user->createToken('refresh', ['refresh'], now()->addDays(7))->plainTextToken,
        'token_type'    => 'Bearer',
    ], 200);
}
```

### JSON Resource — Consistent API Response Shape
```php
// app/Http/Resources/Api/AccountResource.php
class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'name'            => $this->name,
            'type'            => $this->type,
            'balance'         => (float) $this->balance,
            'opening_balance' => (float) $this->opening_balance,
            'currency'        => $this->currency,
            'is_active'       => $this->is_active,
            'created_at'      => $this->created_at->toISOString(),
            'updated_at'      => $this->updated_at->toISOString(),
        ];
    }
}
```

### Error Handling — Custom Exception Handler for API
```php
// app/Exceptions/Handler.php — JSON response for API routes
public function render($request, Throwable $e): Response
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
    }
    return parent::render($request, $e);
}
```

### Lighthouse — N+1 Safe Account Query
```graphql
# graphql/schema.graphql
type Query {
    accounts: [Account!]! @paginate(defaultCount: 20) @guard
    account(id: ID! @eq): Account @find @guard
}

type Account {
    id: ID!
    name: String!
    balance: Float!
    # @with prevents N+1 when resolving transactions
    transactions: [Transaction!]! @hasMany
    creditCards: [CreditCard!]! @hasMany
}
```

```php
// Lighthouse config — point guard to sanctum
// config/lighthouse.php
'guards' => ['sanctum'],
```

### Lighthouse — Custom Resolver for Aggregated Query (API-17)
```php
// app/GraphQL/Queries/MonthlyCashflow.php
namespace App\GraphQL\Queries;

use App\Models\Transaction;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class MonthlyCashflow
{
    public function __invoke(mixed $root, array $args, GraphQLContext $context, ResolveInfo $info): array
    {
        $user   = $context->user();
        $year   = $args['year'];
        $month  = $args['month'];

        return Transaction::query()
            ->where('user_id', $user->id)
            ->whereYear('date', $year)
            ->whereMonth('date', $month)
            ->selectRaw('SUM(CASE WHEN type = "income" THEN amount ELSE 0 END) as total_income')
            ->selectRaw('SUM(CASE WHEN type = "expense" THEN amount ELSE 0 END) as total_expense')
            ->first()
            ->toArray();
    }
}
```

```graphql
# In schema.graphql
type MonthlyCashflow {
    total_income: Float!
    total_expense: Float!
}
extend type Query {
    monthlyCashflow(year: Int!, month: Int!): MonthlyCashflow! 
        @guard 
        @field(resolver: "App\\GraphQL\\Queries\\MonthlyCashflow")
}
```

### Feature Test — API Endpoint with Sanctum
```php
// tests/Feature/Api/AccountApiTest.php
use Laravel\Sanctum\Sanctum;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_list_own_accounts(): void
    {
        $user    = User::factory()->create();
        $other   = User::factory()->create();
        $myAcct  = Account::factory()->create(['user_id' => $user->id]);
        $theirs  = Account::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/accounts');

        $response->assertOk()
            ->assertJsonFragment(['id' => $myAcct->id])
            ->assertJsonMissing(['id' => $theirs->id]);
    }

    /** @test */
    public function unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/accounts')->assertUnauthorized();
    }
}
```

---

## State of the Art

| Old Approach | Current Approach | Notes |
|--------------|------------------|-------|
| `tymon/jwt-auth` | Sanctum API tokens | Sanctum is the Laravel-first solution; JWT package requires separate maintenance |
| Manual filter query params | `spatie/laravel-query-builder` | Standard across Laravel ecosystem since ~2019 |
| L5-Swagger annotations | Scribe auto-generation | Scribe dramatically reduces annotation boilerplate |
| Lighthouse `@middleware` | Lighthouse `@guard` | `@guard` is the current Lighthouse 6.x auth pattern |

**Deprecated/outdated:**
- `tymon/jwt-auth`: Not maintained for PHP 8.x; replaced by `php-open-source-saver/jwt-auth` fork — but Sanctum is simpler for this use case
- `dingo/api`: Abandoned; do not use for Laravel 12

---

## Open Questions

1. **Sanctum token expiry for refresh tokens**
   - What we know: Sanctum 4.x supports `expiration` in `config/sanctum.php` as a global setting (minutes)
   - What's unclear: Whether per-token expiry (different for access vs refresh) needs the `expiration` parameter on `createToken()` — this was added in Sanctum 3.x but should be verified against 4.x API
   - Recommendation: Use `createToken('name', ['*'], CarbonInterface $expiresAt)` — the 3rd parameter overrides global expiration; test in Wave 1 task

2. **GraphQL auth for mutations: `@guard` vs `@can` directive**
   - What we know: `@guard` enforces authentication; `@can` enforces authorization (policy check)
   - What's unclear: Whether `@can(ability: "update", find: "id")` correctly resolves the model for policy check
   - Recommendation: Use both — `@guard` on queries/mutations + `@can` for write operations. Fallback: implement authorization inside custom resolvers.

3. **Existing `ApiRateLimitMiddleware` retirement**
   - What we know: Custom middleware exists and is registered as `api_rate_limit` alias
   - What's unclear: Whether any routes currently use `api_rate_limit` alias vs the framework's `throttle:` middleware
   - Recommendation: Replace with Laravel named rate limiters (`throttle:api-read`, `throttle:api-write`); remove custom middleware after migration.

---

## Environment Availability

| Dependency | Required By | Available | Version | Fallback |
|------------|------------|-----------|---------|----------|
| PHP | Core | ✓ | 8.5.4 | — |
| Composer | Package install | ✓ | 2.9.5 | — |
| `laravel/sanctum` | Auth (API-01–04) | ✓ | v4.3.1 | — |
| `nuwave/lighthouse` | GraphQL (API-15–18) | ✓ | v6.66.0 | — |
| `spatie/laravel-permission` | RBAC | ✓ | ^7.2 | — |
| `spatie/laravel-query-builder` | Filtering (API-12–13) | ✗ | — | Manual query params (fragile) |
| `knuckleswtf/scribe` | Docs (API-19) | ✗ | — | L5-Swagger (more annotations) |
| SQLite in-memory | Tests | ✓ | (built-in) | — |

**Missing dependencies with no fallback:** None — all are installable via Composer.

**Missing dependencies with fallback:**
- `spatie/laravel-query-builder` — fallback is manual filter parsing (not recommended)
- `knuckleswtf/scribe` — fallback is `darkaonline/l5-swagger` with manual annotations

---

## Validation Architecture

### Test Framework
| Property | Value |
|----------|-------|
| Framework | PHPUnit 11.5.3 |
| Config file | `phpunit.xml` |
| Quick run command | `php artisan test tests/Feature/Api --stop-on-failure` |
| Full suite command | `composer test` (clears config + runs all tests) |

### Phase Requirements → Test Map

| Req ID | Behavior | Test Type | Automated Command | File Exists? |
|--------|----------|-----------|-------------------|-------------|
| API-01 | Login returns access + refresh tokens | Feature | `php artisan test tests/Feature/Api/AuthApiTest.php` | ❌ Wave 0 |
| API-02 | Refresh endpoint issues new access token | Feature | `php artisan test tests/Feature/Api/AuthApiTest.php` | ❌ Wave 0 |
| API-03 | Logout invalidates all tokens | Feature | `php artisan test tests/Feature/Api/AuthApiTest.php` | ❌ Wave 0 |
| API-04 | User B cannot access User A's data | Feature | `php artisan test tests/Feature/Api/AccountApiTest.php` | ❌ Wave 0 |
| API-05 | 429 after 100 reads or 20 writes per minute | Feature | `php artisan test tests/Feature/Api/RateLimitTest.php` | ❌ Wave 0 |
| API-06 | CRUD accounts via REST | Feature | `php artisan test tests/Feature/Api/AccountApiTest.php` | ❌ Wave 0 |
| API-07 | CRUD transactions with filter | Feature | `php artisan test tests/Feature/Api/TransactionApiTest.php` | ❌ Wave 0 |
| API-08 | CRUD loans + nested payments | Feature | `php artisan test tests/Feature/Api/LoanApiTest.php` | ❌ Wave 0 |
| API-09 | CRUD credit cards + cycles/expenses | Feature | `php artisan test tests/Feature/Api/CreditCardApiTest.php` | ❌ Wave 0 |
| API-10 | CRUD subscriptions | Feature | `php artisan test tests/Feature/Api/SubscriptionApiTest.php` | ❌ Wave 0 |
| API-11 | Cursor pagination returns correct meta | Feature | included in resource tests above | ❌ Wave 0 |
| API-12 | Sort param orders results | Feature | included in resource tests above | ❌ Wave 0 |
| API-13 | Filter by status/category/date | Feature | included in resource tests above | ❌ Wave 0 |
| API-14 | 422 returns field-level error detail | Feature | included in resource tests above | ❌ Wave 0 |
| API-15 | GraphQL accounts query with nested rels, no N+1 | Feature | `php artisan test tests/Feature/Api/GraphQL/AccountGraphQLTest.php` | ❌ Wave 0 |
| API-16 | GraphQL mutation creates/updates/deletes | Feature | `php artisan test tests/Feature/Api/GraphQL/MutationTest.php` | ❌ Wave 0 |
| API-17 | Aggregated cashflow query returns correct totals | Feature | `php artisan test tests/Feature/Api/GraphQL/CashflowQueryTest.php` | ❌ Wave 0 |
| API-18 | Introspection returns all types | Feature | included in GraphQL tests | ❌ Wave 0 |
| API-19 | `/api/docs` serves Swagger UI | Smoke | `curl -s http://localhost/api/docs` returns 200 | Manual |
| API-20 | Paginated endpoints < 500ms | Smoke | `php artisan test --stop-on-failure` + timing assertions | ❌ Wave 0 |

### Sampling Rate
- **Per task commit:** `php artisan test tests/Feature/Api --stop-on-failure`
- **Per wave merge:** `composer test` (full suite, 110 existing + new API tests)
- **Phase gate:** Full suite green before `/gsd-verify-work`

### Wave 0 Gaps
- [ ] `tests/Feature/Api/AuthApiTest.php` — covers API-01, API-02, API-03
- [ ] `tests/Feature/Api/AccountApiTest.php` — covers API-04, API-06, API-11, API-12, API-13, API-14
- [ ] `tests/Feature/Api/TransactionApiTest.php` — covers API-07
- [ ] `tests/Feature/Api/LoanApiTest.php` — covers API-08
- [ ] `tests/Feature/Api/CreditCardApiTest.php` — covers API-09
- [ ] `tests/Feature/Api/SubscriptionApiTest.php` — covers API-10
- [ ] `tests/Feature/Api/RateLimitTest.php` — covers API-05
- [ ] `tests/Feature/Api/GraphQL/AccountGraphQLTest.php` — covers API-15, API-18
- [ ] `tests/Feature/Api/GraphQL/MutationTest.php` — covers API-16
- [ ] `tests/Feature/Api/GraphQL/CashflowQueryTest.php` — covers API-17
- [ ] Framework install: `composer require spatie/laravel-query-builder knuckleswtf/scribe --dev`

---

## Sources

### Primary (HIGH confidence)
- Composer lock / `composer show` — installed package versions verified directly
- `app/Http/Middleware/ApiRateLimitMiddleware.php` — existing rate limiter code read directly
- `config/lighthouse.php` — Lighthouse configuration read directly (guards = null confirmed)
- `app/Policies/` — all 11 policies confirmed as existing
- `app/Providers/AppServiceProvider.php` — observer registration confirmed
- `graphql/schema.graphql` — schema confirmed as User-only; needs full extension
- `app/Traits/HasUserScoping.php` — trait implementation read directly
- Grep results — confirmed which models use `HasUserScoping`

### Secondary (MEDIUM confidence)
- Laravel Sanctum 4.x docs patterns — token creation with `CarbonInterface $expiresAt`
- Lighthouse 6.x `@guard` directive documentation pattern
- Scribe v4 configuration pattern for Laravel

### Tertiary (LOW confidence — flag for validation)
- Sanctum per-token expiry exact API signature in v4.3.1 (verify in Wave 1 implementation)
- Lighthouse `@can(find: "id")` resolving model for policy check (verify against Lighthouse 6.66 changelog)

---

## Metadata

**Confidence breakdown:**
- Standard stack: HIGH — packages verified via `composer show`; all installed versions confirmed
- Architecture: HIGH — all existing files read directly; patterns from live codebase
- Pitfalls: HIGH — verified by reading actual code (HasUserScoping gaps found by grep, guard config confirmed as null)
- Testing: HIGH — test infrastructure verified from `phpunit.xml` and existing test files

**Research date:** 2026-04-21  
**Valid until:** 2026-05-21 (30 days; Lighthouse and Scribe are stable)
