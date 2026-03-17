<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Policies\AccountPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_others_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse((new AccountPolicy())->update($user, $account));
    }

    public function test_user_can_access_own_account(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $this->assertTrue((new AccountPolicy())->update($user, $account));
    }
}
