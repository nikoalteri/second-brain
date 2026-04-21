---
plan: 7
phase: 6
title: "Feature Tests — Auth, REST CRUD, User Scoping, GraphQL"
wave: 4
depends_on: [2, 3, 4, 5]
requirements: [API-01, API-02, API-03, API-04, API-06, API-07, API-08, API-09, API-10, API-11, API-12, API-13, API-14, API-15, API-16, API-17, API-20]
files_modified:
  - tests/Feature/Api/AuthApiTest.php
  - tests/Feature/Api/AccountApiTest.php
  - tests/Feature/Api/TransactionApiTest.php
  - tests/Feature/Api/LoanApiTest.php
  - tests/Feature/Api/CreditCardApiTest.php
  - tests/Feature/Api/SubscriptionApiTest.php
  - tests/Feature/Api/GraphQLApiTest.php
autonomous: true

must_haves:
  truths:
    - "All 110 existing tests still pass after Plan 7 (no regressions)"
    - "Auth tests: login returns tokens, logout invalidates them, refresh issues new access token"
    - "Scoping tests: User A cannot list or retrieve User B's resources across all 5 endpoints"
    - "Error tests: 401 without token, 403 for other user's resource, 404 for missing resource, 422 for bad input"
    - "Pagination tests: index endpoints return cursor-paginated shape with next_cursor key"
    - "Filter tests: ?filter[is_active]=true on accounts returns only matching records"
    - "GraphQL tests: accounts query returns only auth user's data, createAccount mutation creates record"
    - "GraphQL scoping test: user cannot access another user's loan via GraphQL query"
  artifacts:
    - path: "tests/Feature/Api/AuthApiTest.php"
      provides: "Login, refresh, logout, and 401 tests"
      contains: "authenticated_user_can_login"
    - path: "tests/Feature/Api/AccountApiTest.php"
      provides: "CRUD + scoping + filter + pagination tests"
      contains: "user_cannot_view_another_users_account"
    - path: "tests/Feature/Api/GraphQLApiTest.php"
      provides: "Query, mutation, and auth guard tests"
      contains: "monthlyCashflow"
  key_links:
    - from: "tests/Feature/Api/AuthApiTest.php"
      to: "app/Http/Controllers/Api/V1/AuthController.php"
      via: "POST /api/v1/auth/login"
      pattern: "api/v1/auth/login"
    - from: "tests/Feature/Api/AccountApiTest.php"
      to: "app/Http/Controllers/Api/V1/AccountController.php"
      via: "Sanctum::actingAs($user)"
      pattern: "Sanctum::actingAs"
---

## Objective

Write feature tests that prove all API requirements work correctly: authentication flows, user scoping (cross-user isolation), CRUD operations, error responses, filtering/pagination, and GraphQL queries/mutations. Tests must use `RefreshDatabase` and `Sanctum::actingAs()` for authentication.

**Purpose:** Automated verification of API-01 through API-20. Provides regression safety for future changes.

**Output:** 7 test files covering auth, 5 REST resources, and GraphQL.

## Tasks

<task id="T1" wave="3">
  <title>Auth Tests + Error Response Tests</title>
  <read_first>
    - app/Http/Controllers/Api/V1/AuthController.php
    - app/Exceptions/Handler.php
    - tests/TestCase.php
    - database/factories/UserFactory.php
  </read_first>
  <action>
**Create `tests/Feature/Api/AuthApiTest.php`:**
```php
<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_login_and_receive_tokens(): void
    {
        $user = User::factory()->create([
            'email'    => 'test@fluxa.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@fluxa.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 1800);
    }

    /** @test */
    public function login_with_invalid_credentials_returns_401(): void
    {
        User::factory()->create(['email' => 'test@fluxa.com', 'password' => bcrypt('correct')]);

        $this->postJson('/api/v1/auth/login', [
            'email'    => 'test@fluxa.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized()
          ->assertJsonPath('message', 'Invalid credentials.');
    }

    /** @test */
    public function login_with_missing_fields_returns_422(): void
    {
        $this->postJson('/api/v1/auth/login', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    /** @test */
    public function authenticated_user_can_logout_and_token_is_invalidated(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        // All tokens deleted — subsequent request must return 401
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    /** @test */
    public function user_can_refresh_access_token(): void
    {
        $user = User::factory()->create([
            'email'    => 'refresh@fluxa.com',
            'password' => bcrypt('password123'),
        ]);

        // Login to get refresh token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'refresh@fluxa.com',
            'password' => 'password123',
        ]);

        $refreshToken = $loginResponse->json('refresh_token');

        // Use refresh token to get new access token
        $response = $this->withToken($refreshToken)
            ->postJson('/api/v1/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonPath('expires_in', 1800);
    }

    /** @test */
    public function unauthenticated_request_to_protected_route_returns_401_json(): void
    {
        $response = $this->getJson('/api/v1/accounts');

        $response->assertUnauthorized()
            ->assertJson(['message' => 'Unauthenticated.']);

        // Must be JSON, not a redirect
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? 'application/json');
    }

    /** @test */
    public function accessing_another_users_resource_returns_403_json(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $account = \App\Models\Account::factory()->create(['user_id' => $userA->id]);

        Sanctum::actingAs($userB);

        $this->getJson("/api/v1/accounts/{$account->id}")
            ->assertForbidden()
            ->assertJson(['message' => 'Forbidden.']);
    }

    /** @test */
    public function requesting_nonexistent_resource_returns_404_json(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/accounts/999999')
            ->assertNotFound()
            ->assertJson(['message' => 'Resource not found.']);
    }

    /** @test */
    public function validation_errors_return_422_with_errors_structure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/accounts', [])
            ->assertUnprocessable()
            ->assertJsonStructure([
                'message',
                'errors',
            ])
            ->assertJsonPath('message', 'Validation failed.');
    }
}
```
  </action>
  <acceptance_criteria>
  - `tests/Feature/Api/AuthApiTest.php` exists
  - File contains `authenticated_user_can_login_and_receive_tokens`
  - File contains `login_with_invalid_credentials_returns_401`
  - File contains `authenticated_user_can_logout_and_token_is_invalidated`
  - File contains `user_can_refresh_access_token`
  - File contains `unauthenticated_request_to_protected_route_returns_401_json`
  - File contains `accessing_another_users_resource_returns_403_json`
  - File contains `requesting_nonexistent_resource_returns_404_json`
  - File contains `validation_errors_return_422_with_errors_structure`
  - `php artisan test tests/Feature/Api/AuthApiTest.php` exits 0 (all tests pass)
  </acceptance_criteria>
</task>

<task id="T2a" wave="3">
  <title>REST CRUD + Scoping + Pagination + Filter Tests — Accounts + Transactions</title>
  <read_first>
    - tests/Feature/Api/AuthApiTest.php
    - database/factories/AccountFactory.php
    - database/factories/TransactionFactory.php
  </read_first>
  <action>
**Create `tests/Feature/Api/AccountApiTest.php`:**
```php
<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function authenticated_user_can_list_own_accounts(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $myAccount    = Account::factory()->create(['user_id' => $user->id]);
        $theirAccount = Account::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/accounts');

        $response->assertOk()
            ->assertJsonFragment(['id' => $myAccount->id])
            ->assertJsonMissing(['id' => $theirAccount->id]);
    }

    /** @test */
    public function accounts_index_returns_cursor_paginated_structure(): void
    {
        $user = User::factory()->create();
        Account::factory()->count(5)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/accounts');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'balance', 'currency', 'is_active']],
                'links' => ['first', 'prev', 'next'],
                'meta'  => ['per_page'],
            ]);
    }

    /** @test */
    public function accounts_index_filters_by_is_active(): void
    {
        $user = User::factory()->create();
        $active   = Account::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        $inactive = Account::factory()->create(['user_id' => $user->id, 'is_active' => false]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/accounts?filter[is_active]=true');

        $response->assertOk()
            ->assertJsonFragment(['id' => $active->id])
            ->assertJsonMissing(['id' => $inactive->id]);
    }

    /** @test */
    public function accounts_index_sorts_by_balance_descending(): void
    {
        $user = User::factory()->create();
        Account::factory()->create(['user_id' => $user->id, 'balance' => 100.00]);
        Account::factory()->create(['user_id' => $user->id, 'balance' => 500.00]);
        Account::factory()->create(['user_id' => $user->id, 'balance' => 250.00]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/accounts?sort=-balance');

        $data = $response->json('data');
        $balances = array_column($data, 'balance');

        $this->assertEquals([500.00, 250.00, 100.00], $balances);
    }

    /** @test */
    public function user_cannot_view_another_users_account(): void
    {
        $userA   = User::factory()->create();
        $userB   = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $userA->id]);

        Sanctum::actingAs($userB);

        $this->getJson("/api/v1/accounts/{$account->id}")
            ->assertForbidden();
    }

    /** @test */
    public function authenticated_user_can_create_account(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/accounts', [
            'name'            => 'My Test Account',
            'type'            => 'checking',
            'opening_balance' => 1000.00,
            'currency'        => 'EUR',
            'is_active'       => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'My Test Account')
            ->assertJsonPath('data.currency', 'EUR');

        $this->assertDatabaseHas('accounts', [
            'user_id'  => $user->id,
            'name'     => 'My Test Account',
            'currency' => 'EUR',
        ]);
    }

    /** @test */
    public function user_can_update_own_account(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/accounts/{$account->id}", ['name' => 'New Name'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');
    }

    /** @test */
    public function user_can_delete_own_account(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->deleteJson("/api/v1/accounts/{$account->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    /** @test */
    public function create_account_does_not_accept_user_id_from_request(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Sanctum::actingAs($userA);

        // Attempt to forge user_id to userB's ID
        $response = $this->postJson('/api/v1/accounts', [
            'name'     => 'Injected Account',
            'type'     => 'checking',
            'currency' => 'EUR',
            'user_id'  => $userB->id,  // Injection attempt
        ]);

        $response->assertCreated();

        // Account must be owned by userA, not userB
        $this->assertDatabaseHas('accounts', [
            'name'    => 'Injected Account',
            'user_id' => $userA->id,
        ]);
        $this->assertDatabaseMissing('accounts', [
            'name'    => 'Injected Account',
            'user_id' => $userB->id,
        ]);
    }
}
```
  </action>
  <acceptance_criteria>
  - `tests/Feature/Api/AccountApiTest.php` exists with at least 8 test methods
  - `AccountApiTest.php` contains `create_account_does_not_accept_user_id_from_request`
  - `AccountApiTest.php` contains `accounts_index_sorts_by_balance_descending`
  - `AccountApiTest.php` contains `assertCreated()` (verifies HTTP 201 on store)
  - `php artisan test tests/Feature/Api/AccountApiTest.php` exits 0
  </acceptance_criteria>
</task>

<task id="T2b" wave="3">
  <title>REST CRUD + Scoping + Filter Tests — Loans, Credit Cards, Subscriptions, Transactions</title>
  <read_first>
    - tests/Feature/Api/AccountApiTest.php
    - database/factories/LoanFactory.php
    - database/factories/CreditCardFactory.php
    - database/factories/SubscriptionFactory.php
  </read_first>
  <action>

**Create `tests/Feature/Api/LoanApiTest.php`:**
```php
<?php

namespace Tests\Feature\Api;

use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_list_own_loans(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $myLoan    = Loan::factory()->create(['user_id' => $user->id]);
        $theirLoan = Loan::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/loans')
            ->assertOk()
            ->assertJsonFragment(['id' => $myLoan->id])
            ->assertJsonMissing(['id' => $theirLoan->id]);
    }

    /** @test */
    public function loan_show_includes_payments(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->create(['user_id' => $user->id]);
        LoanPayment::factory()->count(3)->create(['loan_id' => $loan->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/loans/{$loan->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['payments']]);
    }

    /** @test */
    public function user_cannot_access_another_users_loan(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $loan  = Loan::factory()->create(['user_id' => $userA->id]);

        Sanctum::actingAs($userB);

        $this->getJson("/api/v1/loans/{$loan->id}")
            ->assertForbidden();
    }

    /** @test */
    public function loans_can_be_filtered_by_status(): void
    {
        $user = User::factory()->create();
        $active    = Loan::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $completed = Loan::factory()->create(['user_id' => $user->id, 'status' => 'completed']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/loans?filter[status]=active')
            ->assertOk()
            ->assertJsonFragment(['id' => $active->id])
            ->assertJsonMissing(['id' => $completed->id]);
    }

    /** @test */
    public function user_can_create_loan(): void
    {
        $user    = User::factory()->create();
        $account = \App\Models\Account::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/loans', [
            'name'               => 'Home Loan',
            'account_id'         => $account->id,
            'total_amount'       => 50000.00,
            'monthly_payment'    => 1000.00,
            'withdrawal_day'     => 5,
            'start_date'         => '2026-01-01',
            'total_installments' => 50,
            'paid_installments'  => 0,
            'status'             => 'active',
        ])->assertCreated()
          ->assertJsonPath('data.name', 'Home Loan');

        $this->assertDatabaseHas('loans', [
            'user_id' => $user->id,
            'name'    => 'Home Loan',
        ]);
    }
}
```

**Create `tests/Feature/Api/CreditCardApiTest.php`:**
```php
<?php

namespace Tests\Feature\Api;

use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditCardApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_list_own_credit_cards(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $mine  = CreditCard::factory()->create(['user_id' => $user->id]);
        $theirs = CreditCard::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/credit-cards')
            ->assertOk()
            ->assertJsonFragment(['id' => $mine->id])
            ->assertJsonMissing(['id' => $theirs->id]);
    }

    /** @test */
    public function credit_card_show_includes_cycles(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->getJson("/api/v1/credit-cards/{$card->id}")
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'current_balance', 'cycles']]);
    }

    /** @test */
    public function user_cannot_delete_another_users_credit_card(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $card  = CreditCard::factory()->create(['user_id' => $userA->id]);

        Sanctum::actingAs($userB);

        $this->deleteJson("/api/v1/credit-cards/{$card->id}")
            ->assertForbidden();
    }
}
```

**Create `tests/Feature/Api/SubscriptionApiTest.php`:**
```php
<?php

namespace Tests\Feature\Api;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_list_own_subscriptions(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $mine  = Subscription::factory()->create(['user_id' => $user->id]);
        $theirs = Subscription::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/subscriptions')
            ->assertOk()
            ->assertJsonFragment(['id' => $mine->id])
            ->assertJsonMissing(['id' => $theirs->id]);
    }

    /** @test */
    public function subscriptions_can_be_filtered_by_status(): void
    {
        $user     = User::factory()->create();
        $active   = Subscription::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        $inactive = Subscription::factory()->create(['user_id' => $user->id, 'status' => 'inactive']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/subscriptions?filter[status]=active')
            ->assertOk()
            ->assertJsonFragment(['id' => $active->id])
            ->assertJsonMissing(['id' => $inactive->id]);
    }
}
```

**Create `tests/Feature/Api/TransactionApiTest.php`:**
```php
<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_can_list_own_transactions(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        $myTx    = Transaction::factory()->create(['user_id' => $user->id]);
        $theirTx = Transaction::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/transactions')
            ->assertOk()
            ->assertJsonFragment(['id' => $myTx->id])
            ->assertJsonMissing(['id' => $theirTx->id]);
    }

    /** @test */
    public function transactions_can_be_filtered_by_date_from(): void
    {
        $user    = User::factory()->create();
        $recent  = Transaction::factory()->create(['user_id' => $user->id, 'date' => '2026-06-01']);
        $old     = Transaction::factory()->create(['user_id' => $user->id, 'date' => '2025-01-01']);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/transactions?filter[date_from]=2026-01-01')
            ->assertOk()
            ->assertJsonFragment(['id' => $recent->id])
            ->assertJsonMissing(['id' => $old->id]);
    }

    /** @test */
    public function transactions_index_includes_account_relation(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        Transaction::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/transactions');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'amount', 'date', 'account' => ['id', 'name']]],
            ]);
    }
}
```
  </action>
  <acceptance_criteria>
  - `tests/Feature/Api/LoanApiTest.php` exists with `loan_show_includes_payments`
  - `tests/Feature/Api/CreditCardApiTest.php` exists with `credit_card_show_includes_cycles`
  - `tests/Feature/Api/SubscriptionApiTest.php` exists with `subscriptions_can_be_filtered_by_status`
  - `tests/Feature/Api/TransactionApiTest.php` exists with `transactions_can_be_filtered_by_date_from`
  - `LoanApiTest.php` contains `assertCreated()` (verifies HTTP 201 on loan store)
  - `php artisan test tests/Feature/Api/LoanApiTest.php tests/Feature/Api/CreditCardApiTest.php tests/Feature/Api/SubscriptionApiTest.php tests/Feature/Api/TransactionApiTest.php` exits 0
  </acceptance_criteria>
</task>

<task id="T3" wave="3">
  <title>GraphQL Feature Tests</title>
  <read_first>
    - tests/Feature/Api/AccountApiTest.php
    - graphql/schema.graphql
    - app/GraphQL/Queries/MonthlyCashflow.php
    - database/factories/AccountFactory.php
    - database/factories/TransactionFactory.php
  </read_first>
  <action>
**Create `tests/Feature/Api/GraphQLApiTest.php`:**
```php
<?php

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GraphQLApiTest extends TestCase
{
    use RefreshDatabase;

    private function graphql(string $query, array $variables = [], ?User $user = null): \Illuminate\Testing\TestResponse
    {
        $request = $this->postJson('/graphql', [
            'query'     => $query,
            'variables' => $variables,
        ]);

        return $request;
    }

    private function graphqlAs(User $user, string $query, array $variables = []): \Illuminate\Testing\TestResponse
    {
        Sanctum::actingAs($user);
        return $this->graphql($query, $variables);
    }

    /** @test */
    public function graphql_requires_authentication(): void
    {
        $response = $this->graphql('{ accounts { data { id } } }');

        $response->assertOk(); // GraphQL always returns 200
        $errors = $response->json('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString('Unauthenticated', $errors[0]['message']);
    }

    /** @test */
    public function graphql_accounts_query_returns_only_own_accounts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $myAccount    = Account::factory()->create(['user_id' => $userA->id]);
        $theirAccount = Account::factory()->create(['user_id' => $userB->id]);

        $response = $this->graphqlAs($userA, '
            {
                accounts {
                    data {
                        id
                        name
                        balance
                    }
                }
            }
        ');

        $response->assertOk()->assertJsonPath('errors', null);

        $ids = array_column($response->json('data.accounts.data'), 'id');
        $this->assertContains((string) $myAccount->id, $ids);
        $this->assertNotContains((string) $theirAccount->id, $ids);
    }

    /** @test */
    public function graphql_create_account_mutation_works(): void
    {
        $user = User::factory()->create();

        $response = $this->graphqlAs($user, '
            mutation CreateAccount($input: CreateAccountInput!) {
                createAccount(input: $input) {
                    id
                    name
                    currency
                    balance
                }
            }
        ', [
            'input' => [
                'name'            => 'GraphQL Test Account',
                'type'            => 'checking',
                'currency'        => 'EUR',
                'opening_balance' => 500.00,
                'is_active'       => true,
            ],
        ]);

        $response->assertOk()->assertJsonPath('errors', null);

        $created = $response->json('data.createAccount');
        $this->assertEquals('GraphQL Test Account', $created['name']);
        $this->assertEquals('EUR', $created['currency']);

        $this->assertDatabaseHas('accounts', [
            'name'    => 'GraphQL Test Account',
            'user_id' => $user->id,
        ]);
    }

    /** @test */
    public function graphql_monthly_cashflow_returns_aggregated_data(): void
    {
        $user = User::factory()->create();

        // Create some transactions in Jan 2026
        Transaction::factory()->count(3)->create([
            'user_id' => $user->id,
            'date'    => '2026-01-15',
            'amount'  => 100.00,
        ]);

        $response = $this->graphqlAs($user, '
            {
                monthlyCashflow(year: 2026, month: 1) {
                    year
                    month
                    total_income
                    total_expense
                    net
                }
            }
        ');

        $response->assertOk()->assertJsonPath('errors', null);

        $cashflow = $response->json('data.monthlyCashflow');
        $this->assertEquals(2026, $cashflow['year']);
        $this->assertEquals(1, $cashflow['month']);
        $this->assertIsFloat($cashflow['total_income']);
        $this->assertIsFloat($cashflow['total_expense']);
        $this->assertIsFloat($cashflow['net']);
    }

    /** @test */
    public function graphql_total_by_category_returns_array_of_category_totals(): void
    {
        $user = User::factory()->create();

        Transaction::factory()->count(2)->create([
            'user_id' => $user->id,
            'date'    => '2026-03-10',
        ]);

        $response = $this->graphqlAs($user, '
            {
                totalByCategory(year: 2026, month: 3) {
                    category
                    total
                    count
                }
            }
        ');

        $response->assertOk()->assertJsonPath('errors', null);

        $totals = $response->json('data.totalByCategory');
        $this->assertIsArray($totals);
    }

    /** @test */
    public function graphql_introspection_returns_all_finance_types(): void
    {
        $user = User::factory()->create();

        $response = $this->graphqlAs($user, '
            {
                __schema {
                    types {
                        name
                    }
                }
            }
        ');

        $response->assertOk();

        $typeNames = array_column($response->json('data.__schema.types'), 'name');

        $this->assertContains('Account', $typeNames);
        $this->assertContains('Transaction', $typeNames);
        $this->assertContains('Loan', $typeNames);
        $this->assertContains('CreditCard', $typeNames);
        $this->assertContains('Subscription', $typeNames);
        $this->assertContains('MonthlyCashflow', $typeNames);
        $this->assertContains('CategoryTotal', $typeNames);
    }

    /** @test */
    public function graphql_transactions_query_includes_account_relation(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        Transaction::factory()->create([
            'user_id'    => $user->id,
            'account_id' => $account->id,
        ]);

        $response = $this->graphqlAs($user, '
            {
                transactions {
                    data {
                        id
                        amount
                        account {
                            id
                            name
                        }
                    }
                }
            }
        ');

        $response->assertOk()->assertJsonPath('errors', null);

        $first = $response->json('data.transactions.data.0');
        $this->assertArrayHasKey('account', $first);
        $this->assertEquals($account->id, $first['account']['id']);
    }
}
```

**After creating all test files, run the full test suite to confirm no regressions:**
```bash
php artisan test
```

Expected: All pre-existing 110 tests + new API tests pass. If any existing test fails due to API changes (e.g., routes/api.php rewrite affecting old middleware), fix the regression — do NOT disable the failing test.
  </action>
  <acceptance_criteria>
  - `tests/Feature/Api/GraphQLApiTest.php` exists with at least 7 test methods
  - File contains `graphql_requires_authentication`
  - File contains `graphql_accounts_query_returns_only_own_accounts`
  - File contains `graphql_create_account_mutation_works`
  - File contains `graphql_monthly_cashflow_returns_aggregated_data`
  - File contains `graphql_introspection_returns_all_finance_types`
  - `php artisan test tests/Feature/Api/GraphQLApiTest.php` exits 0
  - `php artisan test` exits 0 (full suite — no regressions)
  </acceptance_criteria>
</task>

## Verification

```bash
# Run only new API tests
php artisan test tests/Feature/Api/ --verbose

# Run full test suite for regression check
php artisan test

# Count passing tests
php artisan test --compact | tail -5
```

Expected: All tests pass. The test count grows from 110 to 110 + all new API tests.

## Success Criteria

- `php artisan test tests/Feature/Api/` passes all tests
- `php artisan test` (full suite) shows 0 failures
- User scoping confirmed by cross-user tests in AccountApiTest, LoanApiTest, CreditCardApiTest, SubscriptionApiTest
- Filter tests confirm QueryBuilder allowedFilters work correctly
- Sort test confirms QueryBuilder allowedSorts work correctly
- Pagination test confirms cursor-paginated shape in index responses
- GraphQL tests confirm @guard works and mutations create records
- No test disables or silences observers — side effects (balance updates) are asserted explicitly

## Output

After completion, create `.planning/phases/06-rest-graphql-api/06-7-SUMMARY.md` with:
- Test counts (new tests added, total passing)
- Any failures encountered and how they were fixed
- Any deviations from plan (e.g., factory adjustments needed)
