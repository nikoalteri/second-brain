<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SoftDeletedReferenceTest extends TestCase
{
    use RefreshDatabase;

    private function expenseType(): TransactionType
    {
        return TransactionType::query()->firstOrCreate(['name' => 'Expenses'], ['is_income' => false]);
    }

    public function test_store_transaction_rejects_soft_deleted_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $account->delete();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'transaction_type_id' => $this->expenseType()->id,
            'amount' => 10,
            'date' => '2026-01-10',
            'description' => 'On deleted account',
        ])->assertUnprocessable()->assertJsonValidationErrors('account_id');

        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
    }

    public function test_graphql_update_credit_card_rejects_soft_deleted_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $deleted = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        $deleted->delete();

        Sanctum::actingAs($user);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation ($id: ID!, $a: ID) { updateCreditCard(id: $id, input: {account_id: $a}) { id } }',
            'variables' => ['id' => $card->id, 'a' => $deleted->id],
        ]);

        $this->assertArrayHasKey('input.account_id', $response->json('errors.0.extensions.validation') ?? []);
        $this->assertSame($account->id, $card->fresh()->account_id);
    }
}
