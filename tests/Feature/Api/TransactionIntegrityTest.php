<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\AccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class TransactionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function expenseType(): TransactionType
    {
        return TransactionType::query()->firstOrCreate(['name' => 'Expenses'], ['is_income' => false]);
    }

    public function test_store_transaction_is_rolled_back_when_balance_update_fails(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'balance' => 100]);

        $this->mock(AccountBalanceService::class, function ($mock) {
            $mock->shouldReceive('handleCreated')->andThrow(new RuntimeException('balance update failed'));
        });

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transactions', [
            'account_id' => $account->id,
            'transaction_type_id' => $this->expenseType()->id,
            'amount' => 10,
            'date' => '2026-01-10',
            'description' => 'Must not persist',
        ])->assertStatus(500);

        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
    }
}
