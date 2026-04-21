---
plan: 4
phase: 6
title: "Loans + Credit Cards + Subscriptions REST Controllers"
wave: 1
depends_on: [1]
requirements: [API-04, API-08, API-09, API-10]
files_modified:
  - app/Http/Controllers/Api/V1/LoanController.php
  - app/Http/Resources/Api/LoanResource.php
  - app/Http/Requests/Api/StoreLoanRequest.php
  - app/Http/Requests/Api/UpdateLoanRequest.php
  - app/Http/Controllers/Api/V1/SubscriptionController.php
  - app/Http/Resources/Api/SubscriptionResource.php
  - app/Http/Requests/Api/StoreSubscriptionRequest.php
  - app/Http/Requests/Api/UpdateSubscriptionRequest.php
  - app/Http/Controllers/Api/V1/CreditCardController.php
  - app/Http/Resources/Api/CreditCardResource.php
  - app/Http/Requests/Api/StoreCreditCardRequest.php
  - app/Http/Requests/Api/UpdateCreditCardRequest.php
autonomous: true

must_haves:
  truths:
    - "GET /api/v1/loans returns only the authenticated user's loans (not other users')"
    - "GET /api/v1/loans/{id} includes nested payments array in response"
    - "GET /api/v1/credit-cards returns only the authenticated user's credit cards"
    - "GET /api/v1/credit-cards/{id} includes current cycle in response"
    - "GET /api/v1/subscriptions returns only the authenticated user's subscriptions"
    - "POST /api/v1/loans sets user_id from auth, returns 201 with loan object"
    - "DELETE /api/v1/credit-cards/{id} for another user's card returns 403"
    - "All three endpoints use cursor pagination and allowedFilters/allowedSorts"
  artifacts:
    - path: "app/Http/Controllers/Api/V1/LoanController.php"
      provides: "CRUD for loans with nested LoanPayments in show() response"
      contains: "QueryBuilder::for(Loan::class)"
    - path: "app/Http/Controllers/Api/V1/CreditCardController.php"
      provides: "CRUD for credit cards with cycle data in show()"
      contains: "QueryBuilder::for(CreditCard::class)"
    - path: "app/Http/Controllers/Api/V1/SubscriptionController.php"
      provides: "CRUD for subscriptions"
      contains: "QueryBuilder::for(Subscription::class)"
    - path: "app/Http/Resources/Api/LoanResource.php"
      provides: "Loan JSON shape with nested payments"
      contains: "whenLoaded('payments'"
    - path: "app/Http/Resources/Api/CreditCardResource.php"
      provides: "CreditCard JSON shape with cycles relation"
      contains: "whenLoaded('cycles'"
  key_links:
    - from: "app/Http/Controllers/Api/V1/LoanController.php"
      to: "app/Models/Loan.php"
      via: "->where('user_id', $request->user()->id)"
      pattern: "where\\('user_id'"
    - from: "app/Http/Controllers/Api/V1/CreditCardController.php"
      to: "app/Policies/CreditCardPolicy.php"
      via: "$this->authorize('view', $creditCard)"
      pattern: "authorize\\("
---

## Objective

Build CRUD controllers and JSON resources for Loans, Credit Cards, and Subscriptions. `Loan` and `CreditCard` do NOT use `HasUserScoping` and need explicit `where('user_id')` scoping. `Subscription` uses `HasUserScoping` but we still add explicit scoping for API clarity and to avoid global scope conflicts with Sanctum auth context.

**Purpose:** Delivers API-08, API-09, API-10 (REST CRUD for remaining 3 resources), and API-04 (user scoping enforced for all 5 resources across Plans 3+4).

**Output:** Three controllers, three resources, six form request classes.

## Tasks

<task id="T1" wave="1">
  <title>LoanController + SubscriptionController + Resources + Requests</title>
  <read_first>
    - app/Models/Loan.php
    - app/Models/LoanPayment.php
    - app/Policies/LoanPolicy.php
    - app/Models/Subscription.php
    - app/Policies/SubscriptionPolicy.php
    - app/Http/Requests/StoreLoanRequest.php
  </read_first>
  <action>
**CRITICAL:** `Loan` does NOT use `HasUserScoping`. Always add `.where('user_id', $request->user()->id)`.

**--- LOAN SECTION ---**

**Create `app/Http/Requests/Api/StoreLoanRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'               => ['required', 'string', 'max:255'],
            'account_id'         => ['required', 'integer', 'exists:accounts,id'],
            'total_amount'       => ['required', 'numeric', 'min:0.01'],
            'monthly_payment'    => ['required', 'numeric', 'min:0.01'],
            'interest_rate'      => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_variable_rate'   => ['boolean'],
            'withdrawal_day'     => ['required', 'integer', 'min:1', 'max:31'],
            'skip_weekends'      => ['boolean'],
            'start_date'         => ['required', 'date'],
            'end_date'           => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_installments' => ['required', 'integer', 'min:1'],
            'paid_installments'  => ['required', 'integer', 'min:0'],
            'remaining_amount'   => ['nullable', 'numeric', 'min:0'],
            'status'             => ['required', Rule::in(['active', 'completed', 'defaulted'])],
        ];
    }
}
```

**Create `app/Http/Requests/Api/UpdateLoanRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'total_amount'     => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'monthly_payment'  => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'interest_rate'    => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'is_variable_rate' => ['sometimes', 'boolean'],
            'status'           => ['sometimes', 'required', Rule::in(['active', 'completed', 'defaulted'])],
        ];
    }
}
```

**Create `app/Http/Resources/Api/LoanResource.php`:**
```php
<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'account_id'         => $this->account_id,
            'total_amount'       => (float) $this->total_amount,
            'monthly_payment'    => (float) $this->monthly_payment,
            'interest_rate'      => (float) $this->interest_rate,
            'is_variable_rate'   => (bool) $this->is_variable_rate,
            'remaining_amount'   => (float) $this->remaining_amount,
            'total_installments' => $this->total_installments,
            'paid_installments'  => $this->paid_installments,
            'status'             => $this->status,
            'start_date'         => $this->start_date?->toDateString(),
            'end_date'           => $this->end_date?->toDateString(),
            'payments'           => $this->whenLoaded('payments', fn () =>
                $this->payments->map(fn ($p) => [
                    'id'         => $p->id,
                    'due_date'   => $p->due_date?->toDateString(),
                    'amount'     => (float) $p->amount,
                    'status'     => $p->status,
                ])
            ),
            'created_at'         => $this->created_at->toISOString(),
            'updated_at'         => $this->updated_at->toISOString(),
        ];
    }
}
```

**Create `app/Http/Controllers/Api/V1/LoanController.php`:**
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLoanRequest;
use App\Http\Requests\Api\UpdateLoanRequest;
use App\Http\Resources\Api\LoanResource;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class LoanController extends Controller
{
    /**
     * @group Loans
     * @authenticated
     * @queryParam filter[status] Filter by loan status. Example: active
     * @queryParam sort Sort field. Example: -start_date
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $loans = QueryBuilder::for(Loan::class)
            ->where('user_id', $request->user()->id)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('is_variable_rate'),
            ])
            ->allowedSorts(['start_date', 'end_date', 'total_amount', 'remaining_amount', 'created_at'])
            ->defaultSort('-created_at')
            ->cursorPaginate($request->integer('per_page', 20));

        return LoanResource::collection($loans);
    }

    /** @group Loans @authenticated */
    public function store(StoreLoanRequest $request): LoanResource
    {
        $this->authorize('create', Loan::class);

        $loan = Loan::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        return new LoanResource($loan);
    }

    /** @group Loans @authenticated */
    public function show(Request $request, Loan $loan): LoanResource
    {
        $this->authorize('view', $loan);

        $loan->load('payments');

        return new LoanResource($loan);
    }

    /** @group Loans @authenticated */
    public function update(UpdateLoanRequest $request, Loan $loan): LoanResource
    {
        $this->authorize('update', $loan);

        $loan->update($request->validated());

        return new LoanResource($loan);
    }

    /** @group Loans @authenticated @response 204 {} */
    public function destroy(Request $request, Loan $loan): Response
    {
        $this->authorize('delete', $loan);

        $loan->delete();

        return response()->noContent();
    }
}
```

**--- SUBSCRIPTION SECTION ---**

**Create `app/Http/Requests/Api/StoreSubscriptionRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:255'],
            'account_id'              => ['required', 'integer', 'exists:accounts,id'],
            'monthly_cost'            => ['nullable', 'numeric', 'min:0'],
            'annual_cost'             => ['nullable', 'numeric', 'min:0'],
            'frequency'               => ['required', Rule::in(['monthly', 'annual', 'biennial'])],
            'day_of_month'            => ['required', 'integer', 'min:1', 'max:31'],
            'next_renewal_date'       => ['required', 'date'],
            'category_id'             => ['nullable', 'integer', 'exists:transaction_categories,id'],
            'auto_create_transaction' => ['boolean'],
            'status'                  => ['required', Rule::in(['active', 'inactive', 'cancelled'])],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ];
    }
}
```

**Create `app/Http/Requests/Api/UpdateSubscriptionRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'required', 'string', 'max:255'],
            'monthly_cost'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'annual_cost'       => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'frequency'         => ['sometimes', 'required', Rule::in(['monthly', 'annual', 'biennial'])],
            'day_of_month'      => ['sometimes', 'required', 'integer', 'min:1', 'max:31'],
            'next_renewal_date' => ['sometimes', 'required', 'date'],
            'status'            => ['sometimes', 'required', Rule::in(['active', 'inactive', 'cancelled'])],
            'notes'             => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
```

**Create `app/Http/Resources/Api/SubscriptionResource.php`:**
```php
<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'account_id'              => $this->account_id,
            'monthly_cost'            => (float) $this->monthly_cost,
            'annual_cost'             => (float) $this->annual_cost,
            'frequency'               => $this->frequency,
            'day_of_month'            => $this->day_of_month,
            'next_renewal_date'       => $this->next_renewal_date?->toDateString(),
            'auto_create_transaction' => (bool) $this->auto_create_transaction,
            'status'                  => $this->status,
            'notes'                   => $this->notes,
            'created_at'              => $this->created_at->toISOString(),
            'updated_at'              => $this->updated_at->toISOString(),
        ];
    }
}
```

**Create `app/Http/Controllers/Api/V1/SubscriptionController.php`:**
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreSubscriptionRequest;
use App\Http\Requests\Api\UpdateSubscriptionRequest;
use App\Http\Resources\Api\SubscriptionResource;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SubscriptionController extends Controller
{
    /**
     * @group Subscriptions
     * @authenticated
     * @queryParam filter[status] Filter by subscription status. Example: active
     * @queryParam filter[frequency] Filter by billing frequency. Example: monthly
     * @queryParam sort Sort field. Example: next_renewal_date
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $subscriptions = QueryBuilder::for(Subscription::class)
            ->where('user_id', $request->user()->id)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('frequency'),
                AllowedFilter::exact('account_id'),
            ])
            ->allowedSorts(['next_renewal_date', 'monthly_cost', 'annual_cost', 'created_at'])
            ->defaultSort('next_renewal_date')
            ->cursorPaginate($request->integer('per_page', 20));

        return SubscriptionResource::collection($subscriptions);
    }

    /** @group Subscriptions @authenticated */
    public function store(StoreSubscriptionRequest $request): SubscriptionResource
    {
        $this->authorize('create', Subscription::class);

        $subscription = Subscription::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        return new SubscriptionResource($subscription);
    }

    /** @group Subscriptions @authenticated */
    public function show(Request $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('view', $subscription);

        return new SubscriptionResource($subscription);
    }

    /** @group Subscriptions @authenticated */
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): SubscriptionResource
    {
        $this->authorize('update', $subscription);

        $subscription->update($request->validated());

        return new SubscriptionResource($subscription);
    }

    /** @group Subscriptions @authenticated @response 204 {} */
    public function destroy(Request $request, Subscription $subscription): Response
    {
        $this->authorize('delete', $subscription);

        $subscription->delete();

        return response()->noContent();
    }
}
```
  </action>
  <acceptance_criteria>
  - `app/Http/Controllers/Api/V1/LoanController.php` contains `QueryBuilder::for(Loan::class)` and `->where('user_id', $request->user()->id)`
  - `app/Http/Controllers/Api/V1/SubscriptionController.php` contains `QueryBuilder::for(Subscription::class)` and `->where('user_id', $request->user()->id)`
  - `app/Http/Resources/Api/LoanResource.php` contains `whenLoaded('payments'`
  - `app/Http/Resources/Api/SubscriptionResource.php` contains `next_renewal_date`
  - `LoanController.php` show() contains `$loan->load('payments')`
  - All 4 request files exist in `app/Http/Requests/Api/` (StoreLoanRequest, UpdateLoanRequest, StoreSubscriptionRequest, UpdateSubscriptionRequest)
  - `php artisan route:list --path=api/v1/loans` exits 0
  - `php artisan route:list --path=api/v1/subscriptions` exits 0
  </acceptance_criteria>
</task>

<task id="T2" wave="1">
  <title>CreditCardController + CreditCardResource + Credit Card Form Requests</title>
  <read_first>
    - app/Models/CreditCard.php
    - app/Models/CreditCardCycle.php
    - app/Policies/CreditCardPolicy.php
  </read_first>
  <action>
**CRITICAL:** `CreditCard` does NOT use `HasUserScoping`. Always scope by `where('user_id', $request->user()->id)`.

**Note on CreditCard enums:** The model uses `CreditCardType`, `CreditCardStatus`, and `InterestCalculationMethod` enums. Form requests must use their string values. Check `app/Enums/CreditCardType.php`, `CreditCardStatus.php`, `InterestCalculationMethod.php` for valid case names before writing rules.

**Create `app/Http/Requests/Api/StoreCreditCardRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditCardRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'account_id'                  => ['required', 'integer', 'exists:accounts,id'],
            'name'                        => ['required', 'string', 'max:255'],
            'type'                        => ['required', Rule::in(['revolving', 'charge'])],
            'credit_limit'                => ['nullable', 'numeric', 'min:0'],
            'fixed_payment'               => ['nullable', 'numeric', 'min:0'],
            'interest_rate'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stamp_duty_amount'           => ['nullable', 'numeric', 'min:0'],
            'statement_day'               => ['required', 'integer', 'min:1', 'max:31'],
            'due_day'                     => ['required', 'integer', 'min:1', 'max:31'],
            'skip_weekends'               => ['boolean'],
            'status'                      => ['required', Rule::in(['active', 'inactive', 'cancelled'])],
            'start_date'                  => ['required', 'date'],
            'interest_calculation_method' => ['nullable', Rule::in(['daily_balance', 'direct_monthly'])],
        ];
    }
}
```

**Create `app/Http/Requests/Api/UpdateCreditCardRequest.php`:**
```php
<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreditCardRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'credit_limit'     => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fixed_payment'    => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'interest_rate'    => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'statement_day'    => ['sometimes', 'required', 'integer', 'min:1', 'max:31'],
            'due_day'          => ['sometimes', 'required', 'integer', 'min:1', 'max:31'],
            'skip_weekends'    => ['sometimes', 'boolean'],
            'status'           => ['sometimes', 'required', Rule::in(['active', 'inactive', 'cancelled'])],
        ];
    }
}
```

**Create `app/Http/Resources/Api/CreditCardResource.php`:**
```php
<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'account_id'                  => $this->account_id,
            'name'                        => $this->name,
            'type'                        => $this->type,
            'credit_limit'                => $this->credit_limit !== null ? (float) $this->credit_limit : null,
            'fixed_payment'               => (float) $this->fixed_payment,
            'interest_rate'               => (float) $this->interest_rate,
            'stamp_duty_amount'           => (float) $this->stamp_duty_amount,
            'statement_day'               => $this->statement_day,
            'due_day'                     => $this->due_day,
            'skip_weekends'               => (bool) $this->skip_weekends,
            'current_balance'             => (float) $this->current_balance,
            'available_credit'            => $this->available_credit,
            'status'                      => $this->status,
            'start_date'                  => $this->start_date?->toDateString(),
            'interest_calculation_method' => $this->interest_calculation_method,
            'cycles'                      => $this->whenLoaded('cycles', fn () =>
                $this->cycles->map(fn ($c) => [
                    'id'             => $c->id,
                    'period_month'   => $c->period_month,
                    'status'         => $c->status,
                    'total_spent'    => (float) $c->total_spent,
                    'total_due'      => (float) $c->total_due,
                    'statement_date' => $c->statement_date?->toDateString(),
                ])
            ),
            'created_at'                  => $this->created_at->toISOString(),
            'updated_at'                  => $this->updated_at->toISOString(),
        ];
    }
}
```

**Create `app/Http/Controllers/Api/V1/CreditCardController.php`:**
```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCreditCardRequest;
use App\Http\Requests\Api\UpdateCreditCardRequest;
use App\Http\Resources\Api\CreditCardResource;
use App\Models\CreditCard;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CreditCardController extends Controller
{
    /**
     * @group Credit Cards
     * @authenticated
     * @queryParam filter[status] Filter by card status. Example: active
     * @queryParam filter[type] Filter by card type (revolving, charge). Example: revolving
     * @queryParam sort Sort field. Example: -current_balance
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $cards = QueryBuilder::for(CreditCard::class)
            ->where('user_id', $request->user()->id)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('account_id'),
            ])
            ->allowedSorts(['name', 'current_balance', 'credit_limit', 'created_at'])
            ->defaultSort('-created_at')
            ->cursorPaginate($request->integer('per_page', 20));

        return CreditCardResource::collection($cards);
    }

    /** @group Credit Cards @authenticated */
    public function store(StoreCreditCardRequest $request): CreditCardResource
    {
        $this->authorize('create', CreditCard::class);

        $card = CreditCard::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        return new CreditCardResource($card);
    }

    /** @group Credit Cards @authenticated */
    public function show(Request $request, CreditCard $creditCard): CreditCardResource
    {
        $this->authorize('view', $creditCard);

        $creditCard->load('cycles');

        return new CreditCardResource($creditCard);
    }

    /** @group Credit Cards @authenticated */
    public function update(UpdateCreditCardRequest $request, CreditCard $creditCard): CreditCardResource
    {
        $this->authorize('update', $creditCard);

        $creditCard->update($request->validated());

        return new CreditCardResource($creditCard);
    }

    /** @group Credit Cards @authenticated @response 204 {} */
    public function destroy(Request $request, CreditCard $creditCard): Response
    {
        $this->authorize('delete', $creditCard);

        $creditCard->delete();

        return response()->noContent();
    }
}
```

**IMPORTANT — Verify enum values:** Before finalizing `StoreCreditCardRequest`, check the actual enum values in:
- `app/Enums/CreditCardType.php` — verify cases are `revolving` and `charge`
- `app/Enums/CreditCardStatus.php` — verify valid status values
- `app/Enums/InterestCalculationMethod.php` — verify valid method values

Update `Rule::in([...])` arrays to match actual enum case values if they differ from above.
  </action>
  <acceptance_criteria>
  - `app/Http/Controllers/Api/V1/CreditCardController.php` exists and contains `QueryBuilder::for(CreditCard::class)`
  - `CreditCardController.php` contains `->where('user_id', $request->user()->id)`
  - `CreditCardController.php` show() contains `$creditCard->load('cycles')`
  - `app/Http/Resources/Api/CreditCardResource.php` contains `whenLoaded('cycles'`
  - `app/Http/Resources/Api/CreditCardResource.php` contains `available_credit`
  - `app/Http/Requests/Api/StoreCreditCardRequest.php` contains `'statement_day'` and `'due_day'`
  - `app/Http/Requests/Api/UpdateCreditCardRequest.php` contains `'sometimes'`
  - `php artisan route:list --path=api/v1/credit-cards` exits 0
  - `php artisan inspire` exits 0 (no PHP class resolution errors)
  </acceptance_criteria>
</task>

## Verification

```bash
php artisan route:list --path=api/v1 | grep -E 'loan|subscription|credit'
php artisan config:clear && php artisan inspire
```

All 5 resource endpoints should appear in `php artisan route:list`:
- GET/POST `api/v1/loans`, GET/PUT/PATCH/DELETE `api/v1/loans/{loan}`
- GET/POST `api/v1/credit-cards`, GET/PUT/PATCH/DELETE `api/v1/credit-cards/{creditCard}`
- GET/POST `api/v1/subscriptions`, GET/PUT/PATCH/DELETE `api/v1/subscriptions/{subscription}`

## Success Criteria

- User A cannot retrieve User B's loans (scoping confirmed by policy + where clause)
- `GET /api/v1/loans/{id}` includes `payments` array with payment objects
- `GET /api/v1/credit-cards/{id}` includes `cycles` array
- `POST /api/v1/credit-cards` creates card with `user_id` from auth token
- `DELETE /api/v1/credit-cards/{id}` for another user's card returns 403 JSON
- Cursor pagination present on all three index endpoints

## Output

After completion, create `.planning/phases/06-rest-graphql-api/06-4-SUMMARY.md`.
