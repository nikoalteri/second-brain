---
plan: 3
phase: 6
title: "Accounts + Transactions REST Controllers"
wave: 2
depends_on: [1]
requirements: [API-06, API-07, API-11, API-12, API-13, API-20]
files_modified:
  - app/Http/Controllers/Api/V1/AccountController.php
  - app/Http/Resources/Api/AccountResource.php
  - app/Http/Requests/Api/StoreAccountRequest.php
  - app/Http/Requests/Api/UpdateAccountRequest.php
  - app/Http/Controllers/Api/V1/TransactionController.php
  - app/Http/Resources/Api/TransactionResource.php
  - app/Http/Requests/Api/StoreTransactionRequest.php
  - app/Http/Requests/Api/UpdateTransactionRequest.php
autonomous: true

must_haves:
  truths:
    - "GET /api/v1/accounts returns only the authenticated user's accounts (not other users' data)"
    - "GET /api/v1/accounts?filter[is_active]=true returns only active accounts"
    - "GET /api/v1/accounts?sort=-balance returns accounts sorted by balance descending"
    - "GET /api/v1/accounts returns cursor-paginated response with next_cursor key"
    - "POST /api/v1/accounts creates account with user_id set from auth (not request body)"
    - "GET /api/v1/transactions?filter[account_id]=1&filter[date_from]=2026-01-01 filters correctly"
    - "All collection queries eager-load relations (no N+1 queries)"
    - "Response shape uses JsonResource (not raw model with hidden fields)"
  artifacts:
    - path: "app/Http/Controllers/Api/V1/AccountController.php"
      provides: "CRUD for accounts with QueryBuilder filter/sort/cursor-paginate"
      contains: "QueryBuilder::for(Account::class)"
    - path: "app/Http/Resources/Api/AccountResource.php"
      provides: "Consistent JSON shape for Account"
      contains: "toArray"
    - path: "app/Http/Controllers/Api/V1/TransactionController.php"
      provides: "CRUD for transactions with QueryBuilder filter/sort/cursor-paginate"
      contains: "QueryBuilder::for(Transaction::class)"
    - path: "app/Http/Resources/Api/TransactionResource.php"
      provides: "Consistent JSON shape for Transaction"
      contains: "toArray"
  key_links:
    - from: "app/Http/Controllers/Api/V1/AccountController.php"
      to: "app/Models/Account.php"
      via: "QueryBuilder::for(Account::class)->where('user_id', $request->user()->id)"
      pattern: "where\\('user_id'"
    - from: "app/Http/Controllers/Api/V1/AccountController.php"
      to: "app/Policies/AccountPolicy.php"
      via: "$this->authorize('view', $account)"
      pattern: "authorize\\("
    - from: "app/Http/Controllers/Api/V1/TransactionController.php"
      to: "app/Models/Transaction.php"
      via: "with(['account', 'category'])"
      pattern: "with\\(\\["
---

## Objective

Build CRUD controllers and JSON resources for Accounts and Transactions. Both controllers must enforce user-scoped queries (no `HasUserScoping` on these models — must add `.where('user_id', ...)` explicitly), use `QueryBuilder` for filtering and sorting, and `cursorPaginate(20)` for pagination.

**Purpose:** Delivers API-06, API-07 (REST CRUD), API-11 (cursor pagination), API-12 (sorting), API-13 (filtering), and API-20 (eager loading for <500ms).

**Output:** Two controllers, two resources, four form request classes.

## Tasks

<task id="T1" wave="1">
  <title>AccountController + AccountResource + Account Form Requests</title>
  <read_first>
    - app/Models/Account.php
    - app/Policies/AccountPolicy.php
    - app/Http/Requests/StoreAccountRequest.php
  </read_first>
  <action>
**CRITICAL:** `Account` model does NOT use `HasUserScoping`. Every query MUST include `.where('user_id', $request->user()->id)`.

**Step 1 — Create `app/Http/Requests/Api/StoreAccountRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy checked in controller
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'type'            => ['required', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'currency'        => ['required', 'string', 'size:3'],
            'is_active'       => ['boolean'],
        ];
    }
}
```

**Step 2 — Create `app/Http/Requests/Api/UpdateAccountRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'type'      => ['sometimes', 'required', 'string', 'max:50'],
            'currency'  => ['sometimes', 'required', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
```

**Step 3 — Create `app/Http/Resources/Api/AccountResource.php`:**
```php
<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'is_active'       => (bool) $this->is_active,
            'created_at'      => $this->created_at->toISOString(),
            'updated_at'      => $this->updated_at->toISOString(),
        ];
    }
}
```

**Step 4 — Create `app/Http/Controllers/Api/V1/AccountController.php`:**
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAccountRequest;
use App\Http\Requests\Api\UpdateAccountRequest;
use App\Http\Resources\Api\AccountResource;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class AccountController extends Controller
{
    /**
     * List all accounts for the authenticated user.
     *
     * @group Accounts
     * @authenticated
     * @queryParam filter[type] Filter by account type. Example: checking
     * @queryParam filter[is_active] Filter by active status. Example: true
     * @queryParam filter[currency] Filter by currency code. Example: EUR
     * @queryParam sort Sort field (prefix - for descending). Example: -balance
     * @queryParam cursor Opaque cursor for pagination.
     * @queryParam per_page Items per page (default 20). Example: 20
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = QueryBuilder::for(Account::class)
            ->where('user_id', $request->user()->id)
            ->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('currency'),
            ])
            ->allowedSorts(['name', 'balance', 'opening_balance', 'created_at'])
            ->defaultSort('-created_at')
            ->cursorPaginate($request->integer('per_page', 20));

        return AccountResource::collection($accounts);
    }

    /**
     * Create a new account.
     *
     * @group Accounts
     * @authenticated
     */
    public function store(StoreAccountRequest $request): Response
    {
        $this->authorize('create', Account::class);

        $account = Account::create(array_merge($request->validated(), [
            'user_id'         => $request->user()->id,
            'balance'         => $request->validated('opening_balance', 0),
        ]));

        return (new AccountResource($account))->response()->setStatusCode(201);
    }

    /**
     * Get a single account.
     *
     * @group Accounts
     * @authenticated
     * @urlParam account integer required The account ID. Example: 1
     */
    public function show(Request $request, Account $account): AccountResource
    {
        $this->authorize('view', $account);

        return new AccountResource($account);
    }

    /**
     * Update an account.
     *
     * @group Accounts
     * @authenticated
     * @urlParam account integer required The account ID. Example: 1
     */
    public function update(UpdateAccountRequest $request, Account $account): AccountResource
    {
        $this->authorize('update', $account);

        $account->update($request->validated());

        return new AccountResource($account);
    }

    /**
     * Delete an account.
     *
     * @group Accounts
     * @authenticated
     * @urlParam account integer required The account ID. Example: 1
     * @response 204 {}
     */
    public function destroy(Request $request, Account $account): Response
    {
        $this->authorize('delete', $account);

        $account->delete();

        return response()->noContent();
    }
}
```
  </action>
  <acceptance_criteria>
  - `app/Http/Controllers/Api/V1/AccountController.php` exists
  - `AccountController.php` contains `QueryBuilder::for(Account::class)`
  - `AccountController.php` contains `->where('user_id', $request->user()->id)`
  - `AccountController.php` contains `allowedFilters(`
  - `AccountController.php` contains `allowedSorts(`
  - `AccountController.php` contains `cursorPaginate(`
  - `AccountController.php` contains `$this->authorize(` (at least once per write method)
  - `AccountController.php` contains `'user_id' => $request->user()->id` in store()
  - `AccountController.php` store() returns HTTP 201: contains `->response()->setStatusCode(201)` (NOT `return new AccountResource`)
  - `app/Http/Resources/Api/AccountResource.php` exists and contains `toArray`
  - `app/Http/Requests/Api/StoreAccountRequest.php` contains `'currency'` rule
  - `app/Http/Requests/Api/UpdateAccountRequest.php` contains `'sometimes'`
  </acceptance_criteria>
</task>

<task id="T2" wave="1">
  <title>TransactionController + TransactionResource + Transaction Form Requests</title>
  <read_first>
    - app/Models/Transaction.php
    - app/Policies/TransactionPolicy.php
    - app/Models/TransactionCategory.php
  </read_first>
  <action>
**CRITICAL:** `Transaction` model does NOT use `HasUserScoping`. Must explicitly scope by `user_id`.

**Step 1 — Create `app/Http/Requests/Api/StoreTransactionRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id'              => ['required', 'integer', 'exists:accounts,id'],
            'transaction_type_id'     => ['required', 'integer', 'exists:transaction_types,id'],
            'transaction_category_id' => ['nullable', 'integer', 'exists:transaction_categories,id'],
            'amount'                  => ['required', 'numeric', 'min:0.01'],
            'date'                    => ['required', 'date'],
            'description'             => ['required', 'string', 'max:255'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
            'is_transfer'             => ['boolean'],
            'to_account_id'           => ['nullable', 'integer', 'exists:accounts,id', 'different:account_id'],
        ];
    }
}
```

**Step 2 — Create `app/Http/Requests/Api/UpdateTransactionRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id'              => ['sometimes', 'required', 'integer', 'exists:accounts,id'],
            'transaction_type_id'     => ['sometimes', 'required', 'integer', 'exists:transaction_types,id'],
            'transaction_category_id' => ['sometimes', 'nullable', 'integer', 'exists:transaction_categories,id'],
            'amount'                  => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'date'                    => ['sometimes', 'required', 'date'],
            'description'             => ['sometimes', 'required', 'string', 'max:255'],
            'notes'                   => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
```

**Step 3 — Create `app/Http/Resources/Api/TransactionResource.php`:**
```php
<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'account_id'              => $this->account_id,
            'transaction_type_id'     => $this->transaction_type_id,
            'transaction_category_id' => $this->transaction_category_id,
            'amount'                  => (float) $this->amount,
            'date'                    => $this->date?->toDateString(),
            'description'             => $this->description,
            'notes'                   => $this->notes,
            'is_transfer'             => (bool) $this->is_transfer,
            'account'                 => $this->whenLoaded('account', fn () => [
                'id'   => $this->account->id,
                'name' => $this->account->name,
            ]),
            'category'                => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'created_at'              => $this->created_at->toISOString(),
            'updated_at'              => $this->updated_at->toISOString(),
        ];
    }
}
```

**Step 4 — Create `app/Http/Controllers/Api/V1/TransactionController.php`:**
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Requests\Api\UpdateTransactionRequest;
use App\Http\Resources\Api\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TransactionController extends Controller
{
    /**
     * List transactions for the authenticated user.
     *
     * @group Transactions
     * @authenticated
     * @queryParam filter[account_id] Filter by account ID. Example: 1
     * @queryParam filter[transaction_category_id] Filter by category ID. Example: 3
     * @queryParam filter[date_from] Filter transactions on or after this date. Example: 2026-01-01
     * @queryParam filter[date_to] Filter transactions on or before this date. Example: 2026-12-31
     * @queryParam sort Sort field (prefix - for descending). Example: -date
     * @queryParam cursor Opaque cursor for pagination.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $transactions = QueryBuilder::for(Transaction::class)
            ->where('user_id', $request->user()->id)
            ->with(['account', 'category'])
            ->allowedFilters([
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('transaction_category_id'),
                AllowedFilter::scope('date_from', 'dateFrom'),
                AllowedFilter::scope('date_to', 'dateTo'),
                AllowedFilter::exact('is_transfer'),
            ])
            ->allowedSorts(['date', 'amount', 'created_at', 'description'])
            ->defaultSort('-date')
            ->cursorPaginate($request->integer('per_page', 20));

        return TransactionResource::collection($transactions);
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function store(StoreTransactionRequest $request): Response
    {
        $this->authorize('create', Transaction::class);

        $transaction = Transaction::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        $transaction->load(['account', 'category']);

        return (new TransactionResource($transaction))->response()->setStatusCode(201);
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function show(Request $request, Transaction $transaction): TransactionResource
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'category']);

        return new TransactionResource($transaction);
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): TransactionResource
    {
        $this->authorize('update', $transaction);

        $transaction->update($request->validated());
        $transaction->load(['account', 'category']);

        return new TransactionResource($transaction);
    }

    /**
     * @group Transactions
     * @authenticated
     * @response 204 {}
     */
    public function destroy(Request $request, Transaction $transaction): Response
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->noContent();
    }
}
```

**Step 5 — Add date filter scopes to Transaction model:**
Open `app/Models/Transaction.php` and add these two scope methods:
```php
public function scopeDateFrom($query, string $date)
{
    return $query->where('date', '>=', $date);
}

public function scopeDateTo($query, string $date)
{
    return $query->where('date', '<=', $date);
}
```
  </action>
  <acceptance_criteria>
  - `app/Http/Controllers/Api/V1/TransactionController.php` exists
  - `TransactionController.php` contains `QueryBuilder::for(Transaction::class)`
  - `TransactionController.php` contains `->where('user_id', $request->user()->id)`
  - `TransactionController.php` contains `->with(['account', 'category'])`
  - `TransactionController.php` contains `AllowedFilter::scope('date_from'`
  - `TransactionController.php` contains `cursorPaginate(`
  - `TransactionController.php` store() returns HTTP 201: contains `->response()->setStatusCode(201)` (NOT `return new TransactionResource`)
  - `app/Http/Resources/Api/TransactionResource.php` contains `whenLoaded('account'`
  - `app/Models/Transaction.php` contains `scopeDateFrom`
  - `app/Models/Transaction.php` contains `scopeDateTo`
  - `app/Http/Requests/Api/StoreTransactionRequest.php` contains `'different:account_id'`
  - `php artisan route:list --path=api/v1/transactions` exits 0
  </acceptance_criteria>
</task>

## Verification

```bash
php artisan route:list --path=api/v1/accounts
php artisan route:list --path=api/v1/transactions
php artisan config:clear
# No class resolution errors:
php artisan inspire
```

Manually test with Sanctum token (from Plan 2's login endpoint):
```bash
curl -s -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}' | jq .access_token

curl -s http://localhost:8000/api/v1/accounts \
  -H "Authorization: Bearer {token}" | jq .
```

## Success Criteria

- `GET /api/v1/accounts` returns `{"data": [...], "links": {...}, "meta": {...}}` with cursor pagination
- `GET /api/v1/accounts?filter[is_active]=true` returns only active accounts
- `GET /api/v1/accounts?sort=-balance` returns descending by balance
- User A cannot see User B's accounts (user_id scoping enforced)
- `GET /api/v1/transactions?filter[date_from]=2026-01-01` filters correctly
- `POST /api/v1/transactions` sets `user_id` from auth, not request body
- No N+1 queries: account + category eager-loaded on all transaction queries

## Output

After completion, create `.planning/phases/06-rest-graphql-api/06-3-SUMMARY.md`.
