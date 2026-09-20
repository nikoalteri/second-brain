<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The @delete directive refuses to run unless at least one argument carries a builder
 * directive (@eq, @whereKey, ...) — without one, Lighthouse treats the query as unscoped
 * and throws "Would modify all models, use an argument to filter." instead of deleting.
 * None of these five mutations had such a directive on their `id` argument, so every
 * delete request from the SPA failed silently behind a generic error toast. This file
 * exists because no test ever exercised these mutations end-to-end, which is exactly why
 * the gap shipped unnoticed.
 */
class GraphQLDeleteMutationsTest extends TestCase
{
    use RefreshDatabase;

    private function graphqlAs(User $user, string $query, array $variables = []): TestResponse
    {
        Sanctum::actingAs($user);

        return $this->postJson('/graphql', ['query' => $query, 'variables' => $variables]);
    }

    public function test_delete_account_mutation_removes_the_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $response = $this->graphqlAs($user, '
            mutation DeleteAccount($id: ID!) { deleteAccount(id: $id) { id } }
        ', ['id' => $account->id]);

        $response->assertOk()->assertJsonPath('errors', null);
        $this->assertEquals((string) $account->id, $response->json('data.deleteAccount.id'));
        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_delete_account_mutation_cannot_delete_another_users_account(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $othersAccount = Account::factory()->create(['user_id' => $other->id]);

        $response = $this->graphqlAs($user, '
            mutation DeleteAccount($id: ID!) { deleteAccount(id: $id) { id } }
        ', ['id' => $othersAccount->id]);

        $response->assertOk()->assertJsonPath('errors', null);
        $this->assertNull($response->json('data.deleteAccount'));
        $this->assertDatabaseHas('accounts', ['id' => $othersAccount->id, 'deleted_at' => null]);
    }

    public function test_delete_transaction_mutation_removes_the_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $type = TransactionType::query()->firstOrCreate(['name' => 'Expenses'], ['is_income' => false]);
        $category = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => 'General',
            'is_active' => true,
        ]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $type->id,
            'transaction_category_id' => $category->id,
        ]);

        $response = $this->graphqlAs($user, '
            mutation DeleteTransaction($id: ID!) { deleteTransaction(id: $id) { id } }
        ', ['id' => $transaction->id]);

        $response->assertOk()->assertJsonPath('errors', null);
        $this->assertEquals((string) $transaction->id, $response->json('data.deleteTransaction.id'));
        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
    }

    public function test_delete_loan_mutation_removes_the_loan(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->create(['user_id' => $user->id]);

        $response = $this->graphqlAs($user, '
            mutation DeleteLoan($id: ID!) { deleteLoan(id: $id) { id } }
        ', ['id' => $loan->id]);

        $response->assertOk()->assertJsonPath('errors', null);
        $this->assertEquals((string) $loan->id, $response->json('data.deleteLoan.id'));
        $this->assertDatabaseMissing('loans', ['id' => $loan->id]);
    }

    public function test_delete_credit_card_mutation_removes_the_credit_card(): void
    {
        $user = User::factory()->create();
        $card = CreditCard::factory()->create(['user_id' => $user->id]);

        $response = $this->graphqlAs($user, '
            mutation DeleteCreditCard($id: ID!) { deleteCreditCard(id: $id) { id } }
        ', ['id' => $card->id]);

        $response->assertOk()->assertJsonPath('errors', null);
        $this->assertEquals((string) $card->id, $response->json('data.deleteCreditCard.id'));
        $this->assertSoftDeleted('credit_cards', ['id' => $card->id]);
    }

    public function test_delete_subscription_mutation_removes_the_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        $response = $this->graphqlAs($user, '
            mutation DeleteSubscription($id: ID!) { deleteSubscription(id: $id) { id } }
        ', ['id' => $subscription->id]);

        $response->assertOk()->assertJsonPath('errors', null);
        $this->assertEquals((string) $subscription->id, $response->json('data.deleteSubscription.id'));
        $this->assertSoftDeleted('subscriptions', ['id' => $subscription->id]);
    }
}
