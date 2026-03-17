<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Loan;
use App\Models\User;
use App\Policies\LoanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_access_others_loan(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $loan = Loan::factory()->create(['user_id' => $otherUser->id]);

        $this->assertFalse((new LoanPolicy())->update($user, $loan));
    }

    public function test_user_can_access_own_loan(): void
    {
        $user = User::factory()->create();
        $loan = Loan::factory()->create(['user_id' => $user->id]);

        $this->assertTrue((new LoanPolicy())->update($user, $loan));
    }
}
