<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GraphQLApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function graphql(string $query, array $variables = []): TestResponse
    {
        return $this->postJson('/graphql', ['query' => $query, 'variables' => $variables]);
    }

    private function graphqlAs(User $user, string $query, array $variables = []): TestResponse
    {
        Sanctum::actingAs($user);

        return $this->graphql($query, $variables);
    }

    // ─── Tests ──────────────────────────────────────────────────────────────────

    public function test_graphql_requires_authentication(): void
    {
        $response = $this->graphql('{ accounts(first: 10) { data { id } } }');

        $response->assertOk();

        // @guard returns an Unauthenticated error in the errors array
        $errors = $response->json('errors');
        $this->assertNotNull($errors, 'Expected GraphQL errors array for unauthenticated request');
    }

    public function test_graphql_accounts_query_returns_only_own_accounts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create accounts without auth (HasUserScoping would override user_id if auth active)
        Account::factory()->count(2)->create(['user_id' => $userA->id]);
        Account::factory()->count(1)->create(['user_id' => $userB->id]);

        $response = $this->graphqlAs($userA, '
            {
                accounts(first: 10) {
                    data { id name }
                    paginatorInfo { total }
                }
            }
        ');

        $response->assertOk()
            ->assertJsonPath('errors', null);

        $this->assertEquals(2, $response->json('data.accounts.paginatorInfo.total'));
    }

    public function test_graphql_create_account_mutation_works(): void
    {
        $user = User::factory()->create();

        $response = $this->graphqlAs($user, '
            mutation CreateAccount($input: CreateAccountInput!) {
                createAccount(input: $input) {
                    id
                    name
                    type
                    currency
                    balance
                    opening_balance
                    is_active
                }
            }
        ', [
            'input' => [
                'name'            => 'GraphQL Account',
                'type'            => 'savings',
                'currency'        => 'EUR',
                'opening_balance' => 500.00,
                'is_active'       => true,
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('errors', null);

        $this->assertEquals('GraphQL Account', $response->json('data.createAccount.name'));
        $this->assertEquals('savings', $response->json('data.createAccount.type'));

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name'    => 'GraphQL Account',
        ]);
    }

    public function test_graphql_monthly_cashflow_returns_aggregated_data(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        // Create an income transaction type and transactions
        $incomeType = TransactionType::query()->firstOrCreate(
            ['name' => 'Earnings'],
            ['is_income' => true]
        );
        Transaction::factory()->create([
            'user_id'             => $user->id,
            'account_id'          => $account->id,
            'transaction_type_id' => $incomeType->id,
            'amount'              => 1000.00,
            'date'                => '2026-03-15',
        ]);
        $expenseType = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['is_income' => false]
        );
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $expenseType->id,
            'amount' => -250.00,
            'date' => '2026-03-20',
        ]);

        $response = $this->graphqlAs($user, '
            query TestCashflow($year: Int!, $month: Int!) {
                monthlyCashflow(year: $year, month: $month) {
                    year
                    month
                    total_income
                    total_expense
                    net
                }
            }
        ', ['year' => 2026, 'month' => 3]);

        $response->assertOk()
            ->assertJsonPath('errors', null);

        $cashflow = $response->json('data.monthlyCashflow');
        $this->assertEquals(2026, $cashflow['year']);
        $this->assertEquals(3, $cashflow['month']);
        $this->assertArrayHasKey('total_income', $cashflow);
        $this->assertArrayHasKey('total_expense', $cashflow);
        $this->assertArrayHasKey('net', $cashflow);
        $this->assertEquals(1000.00, $cashflow['total_income']);
        $this->assertEquals(250.00, $cashflow['total_expense']);
        $this->assertEquals(750.00, $cashflow['net']);
    }

    public function test_graphql_total_by_category_returns_array_of_category_totals(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $incomeType = TransactionType::query()->firstOrCreate(
            ['name' => 'Salary'],
            ['is_income' => true]
        );
        $expenseType = TransactionType::query()->firstOrCreate(
            ['name' => 'Expense'],
            ['is_income' => false]
        );

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $incomeType->id,
            'amount' => 1200,
            'date' => '2026-03-10',
            'transaction_category_id' => null,
        ]);
        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $expenseType->id,
            'amount' => -80,
            'date' => '2026-03-11',
            'transaction_category_id' => null,
        ]);

        $response = $this->graphqlAs($user, '
            query TestTotalByCategory($year: Int!, $month: Int!) {
                totalByCategory(year: $year, month: $month) {
                    category
                    total
                    count
                }
            }
        ', ['year' => 2026, 'month' => 3]);

        $response->assertOk()
            ->assertJsonPath('errors', null);

        $totals = $response->json('data.totalByCategory');
        $this->assertIsArray($totals);
        $this->assertCount(1, $totals);
        $this->assertEquals('Uncategorised', $totals[0]['category']);
        $this->assertEquals(80.0, $totals[0]['total']);
    }

    public function test_graphql_introspection_returns_all_finance_types(): void
    {
        // Introspection doesn't require authentication
        $response = $this->graphql('
            {
                __schema {
                    types {
                        name
                    }
                }
            }
        ');

        $response->assertOk();

        $typeNames = collect($response->json('data.__schema.types'))
            ->pluck('name')
            ->toArray();

        $expectedTypes = [
            'Account',
            'Transaction',
            'Loan',
            'CreditCard',
            'ServiceSubscription', // NOT 'Subscription' — renamed to avoid conflict with Lighthouse built-in
            'MonthlyCashflow',
            'CategoryTotal',
        ];

        foreach ($expectedTypes as $type) {
            $this->assertContains($type, $typeNames, "GraphQL schema is missing type: {$type}");
        }

        // Explicitly confirm 'Subscription' is NOT a user-defined type in the schema
        // (Lighthouse may add a built-in Subscription type for real-time, but it's separate)
        $this->assertNotContains('ServiceSubscription', array_filter($typeNames, fn ($t) => $t === 'Subscription'));
    }

    public function test_graphql_transactions_query_includes_account_relation(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'name' => 'GQL Bank']);

        $type = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['is_income' => false]
        );

        Transaction::factory()->create([
            'user_id'             => $user->id,
            'account_id'          => $account->id,
            'transaction_type_id' => $type->id,
        ]);

        $response = $this->graphqlAs($user, '
            {
                transactions(first: 10) {
                    data {
                        id
                        account {
                            id
                            name
                        }
                    }
                }
            }
        ');

        $response->assertOk()
            ->assertJsonPath('errors', null);

        $firstTx = $response->json('data.transactions.data.0');
        $this->assertArrayHasKey('account', $firstTx);
        $this->assertEquals($account->id, $firstTx['account']['id']);
        $this->assertEquals('GQL Bank', $firstTx['account']['name']);
    }

    public function test_graphql_transaction_categories_query_returns_shared_categories(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $parentA = \App\Models\TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $userA->id,
            'name' => 'Living',
            'is_active' => true,
        ]);

        \App\Models\TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $userA->id,
            'parent_id' => $parentA->id,
            'name' => 'Rent',
            'is_active' => true,
        ]);

        \App\Models\TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $userB->id,
            'name' => 'Travel',
            'is_active' => true,
        ]);

        $response = $this->graphqlAs($userA, '
            {
                transactionCategories {
                    name
                    parent {
                        name
                    }
                }
            }
        ');

        $response->assertOk()
            ->assertJsonPath('errors', null);

        $categoryNames = collect($response->json('data.transactionCategories'))
            ->pluck('name')
            ->all();

        $this->assertContains('Living', $categoryNames);
        $this->assertContains('Rent', $categoryNames);
        $this->assertContains('Travel', $categoryNames);
    }

    public function test_superadmin_graphql_accounts_query_returns_all_accounts(): void
    {
        Role::create(['name' => 'superadmin']);

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        Account::factory()->count(2)->create(['user_id' => $userA->id]);
        Account::factory()->count(1)->create(['user_id' => $userB->id]);

        $response = $this->graphqlAs($superadmin, '
            {
                accounts(first: 10) {
                    paginatorInfo { total }
                }
            }
        ');

        $response->assertOk()
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.accounts.paginatorInfo.total', 3);
    }

    public function test_superadmin_monthly_cashflow_includes_all_users_transactions(): void
    {
        Role::create(['name' => 'superadmin']);

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $accountA = Account::factory()->create(['user_id' => $userA->id]);
        $accountB = Account::factory()->create(['user_id' => $userB->id]);

        $incomeType = TransactionType::query()->firstOrCreate(
            ['name' => 'Salary'],
            ['is_income' => true]
        );

        Transaction::factory()->create([
            'user_id'             => $userA->id,
            'account_id'          => $accountA->id,
            'transaction_type_id' => $incomeType->id,
            'amount'              => 1000.00,
            'date'                => '2026-03-15',
        ]);

        Transaction::factory()->create([
            'user_id'             => $userB->id,
            'account_id'          => $accountB->id,
            'transaction_type_id' => $incomeType->id,
            'amount'              => 500.00,
            'date'                => '2026-03-20',
        ]);

        $response = $this->graphqlAs($superadmin, '
            query TestCashflow($year: Int!, $month: Int!) {
                monthlyCashflow(year: $year, month: $month) {
                    total_income
                }
            }
        ', ['year' => 2026, 'month' => 3]);

        $response->assertOk()
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.monthlyCashflow.total_income', 1500);
    }

    public function test_superadmin_can_create_transaction_for_another_users_account(): void
    {
        Role::create(['name' => 'superadmin']);

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');

        $otherUser = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $otherUser->id]);

        $type = TransactionType::query()->firstOrCreate(
            ['name' => 'Income'],
            ['is_income' => true]
        );

        $response = $this->graphqlAs($superadmin, '
            mutation CreateTransaction($input: CreateTransactionInput!) {
                createTransaction(input: $input) {
                    id
                    account_id
                }
            }
        ', [
            'input' => [
                'account_id' => (string) $account->id,
                'transaction_type_id' => (string) $type->id,
                'amount' => 25.50,
                'date' => '2026-03-22',
                'description' => 'Superadmin test transaction',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('errors', null)
            ->assertJsonPath('data.createTransaction.account_id', (string) $account->id);

        $this->assertDatabaseHas('transactions', [
            'account_id' => $account->id,
            'user_id' => $otherUser->id,
            'description' => 'Superadmin test transaction',
        ]);
    }

    public function test_graphql_create_expense_transaction_stores_negative_amount_and_updates_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create([
            'user_id' => $user->id,
            'balance' => 1000.00,
        ]);

        $expenseType = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['is_income' => false]
        );

        $response = $this->graphqlAs($user, '
            mutation CreateTransaction($input: CreateTransactionInput!) {
                createTransaction(input: $input) {
                    id
                }
            }
        ', [
            'input' => [
                'account_id' => (string) $account->id,
                'transaction_type_id' => (string) $expenseType->id,
                'amount' => 650,
                'date' => '2026-03-22',
                'description' => 'Rent',
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('errors', null);

        $transactionId = $response->json('data.createTransaction.id');

        $this->assertDatabaseHas('transactions', [
            'id' => $transactionId,
            'account_id' => $account->id,
            'amount' => -650.00,
            'competence_month' => '2026-03',
            'description' => 'Rent',
        ]);

        $this->assertSame(
            350.0,
            (float) $account->fresh()->balance,
        );
    }
}
