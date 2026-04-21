<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_own_accounts(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create accounts without auth (so HasUserScoping doesn't override user_id)
        Account::factory()->count(3)->create(['user_id' => $userA->id]);
        Account::factory()->count(2)->create(['user_id' => $userB->id]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/accounts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_accounts_index_returns_cursor_paginated_structure(): void
    {
        $user = User::factory()->create();
        Account::factory()->count(2)->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/accounts');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta'  => ['path', 'per_page'],
            ]);
    }

    public function test_accounts_index_filters_by_is_active(): void
    {
        $user = User::factory()->create();

        Account::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        Account::factory()->create(['user_id' => $user->id, 'is_active' => true]);
        Account::factory()->create(['user_id' => $user->id, 'is_active' => false]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/accounts?filter[is_active]=true');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $account) {
            $this->assertTrue($account['is_active']);
        }
    }

    public function test_accounts_index_sorts_by_balance_descending(): void
    {
        $user = User::factory()->create();

        Account::factory()->create(['user_id' => $user->id, 'balance' => 100.00]);
        Account::factory()->create(['user_id' => $user->id, 'balance' => 500.00]);
        Account::factory()->create(['user_id' => $user->id, 'balance' => 250.00]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/accounts?sort=-balance');

        $response->assertOk();

        $balances = array_column($response->json('data'), 'balance');
        $this->assertEquals([500.00, 250.00, 100.00], $balances);
    }

    /**
     * Due to HasUserScoping global scope, accessing another user's resource
     * returns 404 — route model binding cannot find the record for the acting user.
     */
    public function test_user_cannot_view_another_users_account(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create userA's account without auth
        $userAAccount = Account::factory()->create(['user_id' => $userA->id]);

        Sanctum::actingAs($userB);

        $response = $this->getJson("/api/v1/accounts/{$userAAccount->id}");

        $response->assertStatus(404);
    }

    public function test_authenticated_user_can_create_account(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/accounts', [
            'name'            => 'My Savings Account',
            'type'            => 'savings',
            'opening_balance' => 1000.00,
            'currency'        => 'EUR',
            'is_active'       => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'type', 'balance', 'opening_balance', 'currency', 'is_active']])
            ->assertJsonPath('data.name', 'My Savings Account')
            ->assertJsonPath('data.type', 'savings')
            ->assertJsonPath('data.currency', 'EUR');

        $this->assertDatabaseHas('accounts', [
            'user_id'  => $user->id,
            'name'     => 'My Savings Account',
            'currency' => 'EUR',
        ]);
    }

    public function test_user_can_update_own_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/accounts/{$account->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('accounts', [
            'id'   => $account->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_user_can_delete_own_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/accounts/{$account->id}");

        $response->assertNoContent();

        // Account uses SoftDeletes
        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_create_account_does_not_accept_user_id_from_request(): void
    {
        $user      = User::factory()->create();
        $otherUser = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/accounts', [
            'name'     => 'Injected Account',
            'type'     => 'bank',
            'currency' => 'EUR',
            'user_id'  => $otherUser->id, // Attempt to inject another user's ID
        ]);

        $response->assertStatus(201);

        // The account must belong to the authenticated user, not otherUser
        $this->assertDatabaseHas('accounts', [
            'name'    => 'Injected Account',
            'user_id' => $user->id,
        ]);
        $this->assertDatabaseMissing('accounts', [
            'name'    => 'Injected Account',
            'user_id' => $otherUser->id,
        ]);
    }
}
