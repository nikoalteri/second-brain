<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class LoanWriteAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private function failScheduleGeneration(): void
    {
        $this->mock(LoanScheduleService::class, function ($mock) {
            $mock->shouldReceive('generate')->andThrow(new RuntimeException('schedule failed'));
        });
    }

    public function test_loan_store_is_rolled_back_when_schedule_generation_fails(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $this->failScheduleGeneration();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/loans', [
            'name' => 'Car', 'account_id' => $account->id, 'total_amount' => 1200, 'monthly_payment' => 100,
            'withdrawal_day' => 5, 'start_date' => '2026-01-05', 'total_installments' => 12,
            'paid_installments' => 0, 'remaining_amount' => 1200, 'status' => 'active',
        ])->assertStatus(500);

        $this->assertSame(0, Loan::withoutGlobalScopes()->count());
    }

    public function test_loan_update_is_rolled_back_when_schedule_generation_fails(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'name' => 'Original']);
        $this->failScheduleGeneration();

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/loans/{$loan->id}", ['name' => 'Renamed'])->assertStatus(500);

        $this->assertSame('Original', $loan->fresh()->name);
    }
}
