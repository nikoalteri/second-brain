<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OwnershipValidationTest extends TestCase
{
    use RefreshDatabase;

    private function expenseType(): TransactionType
    {
        return TransactionType::query()->firstOrCreate(['name' => 'Expenses'], ['is_income' => false]);
    }

    public function test_store_transaction_rejects_account_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignAccount = Account::factory()->create(['user_id' => $other->id, 'balance' => 100]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transactions', [
            'account_id' => $foreignAccount->id,
            'transaction_type_id' => $this->expenseType()->id,
            'amount' => 10,
            'date' => '2026-01-10',
            'description' => 'Injected',
        ])->assertUnprocessable()->assertJsonValidationErrors('account_id');

        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
        $this->assertEquals(100, $foreignAccount->fresh()->balance);
    }

    public function test_store_transaction_rejects_category_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $foreignCategory = TransactionCategory::create(['user_id' => $other->id, 'name' => 'Cat '.$other->id, 'is_active' => true]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'transaction_type_id' => $this->expenseType()->id,
            'transaction_category_id' => $foreignCategory->id,
            'amount' => 10,
            'date' => '2026-01-10',
            'description' => 'Injected category',
        ])->assertUnprocessable()->assertJsonValidationErrors('transaction_category_id');

        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
    }

    public function test_update_transaction_rejects_foreign_account_and_category(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $foreignAccount = Account::factory()->create(['user_id' => $other->id]);
        $foreignCategory = TransactionCategory::create(['user_id' => $other->id, 'name' => 'Cat '.$other->id, 'is_active' => true]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $this->expenseType()->id,
        ]);

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/transactions/{$transaction->id}", ['account_id' => $foreignAccount->id])
            ->assertUnprocessable()->assertJsonValidationErrors('account_id');

        $this->putJson("/api/v1/transactions/{$transaction->id}", ['transaction_category_id' => $foreignCategory->id])
            ->assertUnprocessable()->assertJsonValidationErrors('transaction_category_id');

        $this->assertSame($account->id, $transaction->fresh()->account_id);
    }

    public function test_store_transaction_still_accepts_own_account_and_category(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $category = TransactionCategory::create(['user_id' => $user->id, 'name' => 'Cat '.$user->id, 'is_active' => true]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'transaction_type_id' => $this->expenseType()->id,
            'transaction_category_id' => $category->id,
            'amount' => 10,
            'date' => '2026-01-10',
            'description' => 'Legit',
        ])->assertCreated();
    }

    private function loanPayload(int $accountId): array
    {
        return [
            'name' => 'Car loan',
            'account_id' => $accountId,
            'total_amount' => 1200,
            'monthly_payment' => 100,
            'withdrawal_day' => 5,
            'start_date' => '2026-01-05',
            'total_installments' => 12,
            'paid_installments' => 0,
            'status' => 'active',
        ];
    }

    public function test_store_loan_rejects_account_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignAccount = Account::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/loans', $this->loanPayload($foreignAccount->id))
            ->assertUnprocessable()->assertJsonValidationErrors('account_id');

        $this->assertSame(0, Loan::withoutGlobalScopes()->count());
    }

    public function test_graphql_update_loan_rejects_account_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $foreignAccount = Account::factory()->create(['user_id' => $other->id]);
        $loan = Loan::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation ($id: ID!, $accountId: ID) { updateLoan(id: $id, input: {account_id: $accountId}) { id } }',
            'variables' => ['id' => $loan->id, 'accountId' => $foreignAccount->id],
        ]);

        $this->assertArrayHasKey('input.account_id', $response->json('errors.0.extensions.validation') ?? []);
        $this->assertSame($account->id, $loan->fresh()->account_id);
    }

    public function test_graphql_create_loan_rejects_account_of_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $foreignAccount = Account::factory()->create(['user_id' => $other->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation ($accountId: ID!) { createLoan(input: {account_id: $accountId, name: "X", total_amount: 100, monthly_payment: 10, withdrawal_day: 5, start_date: "2026-01-05", total_installments: 10, paid_installments: 0, remaining_amount: 100, status: "active"}) { id } }',
            'variables' => ['accountId' => $foreignAccount->id],
        ]);

        $this->assertArrayHasKey('input.account_id', $response->json('errors.0.extensions.validation') ?? []);
        $this->assertSame(0, Loan::withoutGlobalScopes()->count());
    }

    public function test_graphql_update_loan_accepts_own_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $newAccount = Account::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation ($id: ID!, $accountId: ID) { updateLoan(id: $id, input: {account_id: $accountId}) { id } }',
            'variables' => ['id' => $loan->id, 'accountId' => $newAccount->id],
        ]);

        $this->assertEmpty($response->json('errors'));
        $this->assertSame($newAccount->id, $loan->fresh()->account_id);
    }
}
