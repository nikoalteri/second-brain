<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TransferApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_transfer_moves_balance_between_own_accounts(): void
    {
        $user = User::factory()->create();
        $from = Account::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $to = Account::factory()->create(['user_id' => $user->id, 'balance' => 200]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/transfers', [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 150.5,
            'date' => '2026-08-08',
            'description' => 'Savings top-up',
        ]);

        $response->assertCreated()
            ->assertJsonPath('out.amount', -150.5)
            ->assertJsonPath('in.amount', 150.5);

        $this->assertSame(849.5, (float) $from->fresh()->balance);
        $this->assertSame(350.5, (float) $to->fresh()->balance);

        $out = Transaction::where('account_id', $from->id)->where('is_transfer', true)->first();
        $in = Transaction::where('account_id', $to->id)->where('is_transfer', true)->first();

        $this->assertNotNull($out);
        $this->assertNotNull($in);
        $this->assertSame($out->transfer_pair_id, $in->transfer_pair_id);
        $this->assertTrue($out->is_transfer);
        $this->assertTrue($in->is_transfer);
    }

    public function test_deleting_one_leg_of_a_transfer_removes_the_pair_and_restores_balances(): void
    {
        $user = User::factory()->create();
        $from = Account::factory()->create(['user_id' => $user->id, 'balance' => 1000]);
        $to = Account::factory()->create(['user_id' => $user->id, 'balance' => 200]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transfers', [
            'from_account_id' => $from->id,
            'to_account_id' => $to->id,
            'amount' => 150,
            'date' => '2026-08-08',
        ])->assertCreated();

        $out = Transaction::where('account_id', $from->id)->where('is_transfer', true)->firstOrFail();

        $this->deleteJson("/api/v1/transactions/{$out->id}")->assertNoContent();

        $this->assertSame(1000.0, (float) $from->fresh()->balance);
        $this->assertSame(200.0, (float) $to->fresh()->balance);
        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(2, Transaction::withTrashed()->count());
    }

    public function test_transfer_rejects_same_source_and_destination_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transfers', [
            'from_account_id' => $account->id,
            'to_account_id' => $account->id,
            'amount' => 50,
            'date' => '2026-08-08',
        ])->assertStatus(422)->assertJsonValidationErrors(['from_account_id']);
    }

    public function test_transfer_cannot_use_another_users_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $from = Account::factory()->create(['user_id' => $user->id]);
        $foreignAccount = Account::factory()->create(['user_id' => $otherUser->id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/transfers', [
            'from_account_id' => $from->id,
            'to_account_id' => $foreignAccount->id,
            'amount' => 50,
            'date' => '2026-08-08',
        ])->assertStatus(404);
    }

    public function test_transfer_service_rejects_accounts_from_different_owners_even_when_unscoped(): void
    {
        // Regression guard for the superadmin path: Filament exempts superadmin from
        // HasUserScoping, so its account picker can list accounts across every user. The
        // service itself — not just the scoped API lookup — must refuse to move money
        // between two different people's accounts.
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $from = Account::factory()->create(['user_id' => $userA->id]);
        $to = Account::factory()->create(['user_id' => $userB->id]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(\App\Services\AccountTransferService::class)->transfer($from, $to, 50, '2026-08-08');
    }
}
