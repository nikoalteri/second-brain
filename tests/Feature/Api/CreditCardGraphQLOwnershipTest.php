<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditCardGraphQLOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_graphql_update_credit_card_rejects_foreign_account(): void
    {
        $user = User::factory()->create();
        $ownAccount = Account::factory()->create(['user_id' => $user->id]);
        $foreignAccount = Account::factory()->create(['user_id' => User::factory()->create()->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $ownAccount->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation ($id: ID!, $a: ID) { updateCreditCard(id: $id, input: {account_id: $a}) { id } }',
            'variables' => ['id' => $card->id, 'a' => $foreignAccount->id],
        ]);

        $this->assertArrayHasKey('input.account_id', $response->json('errors.0.extensions.validation') ?? []);
        $this->assertSame($ownAccount->id, $card->fresh()->account_id);
    }

    public function test_graphql_create_credit_card_rejects_foreign_account(): void
    {
        $user = User::factory()->create();
        $foreignAccount = Account::factory()->create(['user_id' => User::factory()->create()->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation ($a: ID!) { createCreditCard(input: {account_id: $a, name: "X", type: "charge", brand: "visa", statement_day: 5, due_day: 20, status: "active", start_date: "2026-01-01"}) { id } }',
            'variables' => ['a' => $foreignAccount->id],
        ]);

        $this->assertArrayHasKey('input.account_id', $response->json('errors.0.extensions.validation') ?? []);
        $this->assertSame(0, CreditCard::withoutGlobalScopes()->count());
    }

    public function test_graphql_update_credit_card_accepts_own_account(): void
    {
        $user = User::factory()->create();
        $ownAccount = Account::factory()->create(['user_id' => $user->id]);
        $newAccount = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $ownAccount->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation ($id: ID!, $a: ID) { updateCreditCard(id: $id, input: {account_id: $a}) { id } }',
            'variables' => ['id' => $card->id, 'a' => $newAccount->id],
        ]);

        $this->assertEmpty($response->json('errors'));
        $this->assertSame($newAccount->id, $card->fresh()->account_id);
    }
}
