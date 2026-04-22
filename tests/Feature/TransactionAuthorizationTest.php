<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Account;
use App\Policies\TransactionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_others_transaction(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $transaction = Transaction::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse((new TransactionPolicy())->update($user, $transaction));
    }

    public function test_user_can_access_own_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
        ]);

        $this->assertTrue((new TransactionPolicy())->update($user, $transaction));
    }
}
