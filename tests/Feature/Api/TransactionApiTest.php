<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Helper to create a transaction for a user+account without active auth.
     */
    private function createTransactionFor(User $user, Account $account, array $attrs = []): Transaction
    {
        $type = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['is_income' => false]
        );

        return Transaction::factory()->create(array_merge([
            'user_id'             => $user->id,
            'account_id'          => $account->id,
            'transaction_type_id' => $type->id,
        ], $attrs));
    }

    public function test_user_can_list_own_transactions(): void
    {
        $userA    = User::factory()->create();
        $userB    = User::factory()->create();
        $accountA = Account::factory()->create(['user_id' => $userA->id]);
        $accountB = Account::factory()->create(['user_id' => $userB->id]);

        $this->createTransactionFor($userA, $accountA);
        $this->createTransactionFor($userA, $accountA);
        $this->createTransactionFor($userB, $accountB);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/transactions');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_transactions_can_be_filtered_by_date_from(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        // Older transaction (before filter date)
        $this->createTransactionFor($user, $account, ['date' => '2025-12-31']);
        // Newer transactions (on or after filter date)
        $this->createTransactionFor($user, $account, ['date' => '2026-01-01']);
        $this->createTransactionFor($user, $account, ['date' => '2026-03-15']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/transactions?filter[date_from]=2026-01-01');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $tx) {
            $this->assertGreaterThanOrEqual('2026-01-01', $tx['date']);
        }
    }

    public function test_transactions_index_includes_account_relation(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'name' => 'My Bank']);

        $this->createTransactionFor($user, $account);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/transactions');

        $response->assertOk();

        $firstItem = $response->json('data.0');

        $this->assertArrayHasKey('account', $firstItem);
        $this->assertEquals($account->id, $firstItem['account']['id']);
        $this->assertEquals('My Bank', $firstItem['account']['name']);
    }
}
