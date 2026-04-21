<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditCardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_credit_cards(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $accountA = Account::factory()->create(['user_id' => $userA->id]);
        $accountB = Account::factory()->create(['user_id' => $userB->id]);

        CreditCard::factory()->count(2)->create(['user_id' => $userA->id, 'account_id' => $accountA->id]);
        CreditCard::factory()->count(3)->create(['user_id' => $userB->id, 'account_id' => $accountB->id]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/credit-cards');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_credit_card_show_includes_cycles(): void
    {
        $user       = User::factory()->create();
        $account    = Account::factory()->create(['user_id' => $user->id]);
        $creditCard = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/credit-cards/{$creditCard->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'cycles']]);
    }

    public function test_user_cannot_delete_another_users_credit_card(): void
    {
        $userA      = User::factory()->create();
        $userB      = User::factory()->create();
        $account    = Account::factory()->create(['user_id' => $userA->id]);
        $creditCard = CreditCard::factory()->create(['user_id' => $userA->id, 'account_id' => $account->id]);

        Sanctum::actingAs($userB);

        $response = $this->deleteJson("/api/v1/credit-cards/{$creditCard->id}");

        // HasUserScoping filters out userA's card for userB → 404
        $response->assertStatus(404);
    }
}
