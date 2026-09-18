<?php

namespace Tests\Unit;

use App\Enums\LoanStatus;
use App\Models\Account;
use App\Models\Loan;
use App\Models\User;
use App\Services\LoanScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoanSyncStatusTest extends TestCase
{
    use RefreshDatabase;

    private function loan(LoanStatus $status, float $totalAmount = 1200): Loan
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        return Loan::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'total_amount' => $totalAmount,
            'remaining_amount' => $totalAmount,
            'paid_installments' => 0,
            'status' => $status,
        ]);
    }

    #[Test]
    public function sync_keeps_a_defaulted_loan_defaulted(): void
    {
        $loan = $this->loan(LoanStatus::DEFAULTED);

        app(LoanScheduleService::class)->syncLoan($loan);

        $this->assertSame(LoanStatus::DEFAULTED, $loan->fresh()->status);
    }

    #[Test]
    public function sync_keeps_an_active_loan_active(): void
    {
        $loan = $this->loan(LoanStatus::ACTIVE);

        app(LoanScheduleService::class)->syncLoan($loan);

        $this->assertSame(LoanStatus::ACTIVE, $loan->fresh()->status);
    }

    #[Test]
    public function sync_completes_a_loan_with_nothing_left_to_repay(): void
    {
        $loan = $this->loan(LoanStatus::DEFAULTED, totalAmount: 0);

        app(LoanScheduleService::class)->syncLoan($loan);

        $this->assertSame(LoanStatus::COMPLETED, $loan->fresh()->status);
    }
}
