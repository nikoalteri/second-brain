<?php

namespace Tests\Unit;

use App\Enums\LoanPaymentStatus;
use App\Models\Account;
use App\Models\Loan;
use App\Models\Transaction;
use App\Services\LoanPaymentPostingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanPaymentPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @test */
    public function it_creates_a_single_negative_transaction_when_payment_is_paid(): void
    {
        Carbon::setTestNow('2026-03-18 12:00:00');

        $account = Account::factory()->create(['balance' => 1000]);

        $loan = Loan::factory()->create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'total_amount' => 1000,
            'monthly_payment' => 250,
            'remaining_amount' => 1000,
            'total_installments' => 4,
            'paid_installments' => 0,
        ]);

        $loan->payments()->create([
            'due_date' => Carbon::parse('2026-03-18'),
            'actual_date' => Carbon::parse('2026-03-18'),
            'amount' => 250,
            'status' => LoanPaymentStatus::PAID,
        ]);

        $this->assertDatabaseCount('transactions', 1);

        $transaction = Transaction::query()->first();

        $this->assertSame(-250.0, (float) $transaction->amount);
        $this->assertSame($loan->account_id, $transaction->account_id);
        $this->assertSame($loan->user_id, $transaction->user_id);
        $this->assertSame('2026-03', $transaction->competence_month);

        $account->refresh();
        $this->assertSame(750.0, (float) $account->balance);
    }

    /** @test */
    public function it_does_not_duplicate_posting_for_paid_payment_updates(): void
    {
        Carbon::setTestNow('2026-03-20 12:00:00');

        $account = Account::factory()->create(['balance' => 1000]);

        $loan = Loan::factory()->create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'total_amount' => 900,
            'monthly_payment' => 300,
            'remaining_amount' => 900,
            'total_installments' => 3,
            'paid_installments' => 0,
        ]);

        $payment = $loan->payments()->create([
            'due_date' => Carbon::parse('2026-03-20'),
            'actual_date' => Carbon::parse('2026-03-20'),
            'amount' => 300,
            'status' => LoanPaymentStatus::PAID,
        ]);

        $payment->update(['notes' => 'updated']);

        $this->assertDatabaseCount('transactions', 1);
    }

    /** @test */
    public function it_soft_deletes_posting_when_future_payment_returns_to_pending(): void
    {
        Carbon::setTestNow('2026-03-20 12:00:00');

        $account = Account::factory()->create(['balance' => 1000]);

        $loan = Loan::factory()->create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'total_amount' => 1000,
            'monthly_payment' => 200,
            'remaining_amount' => 1000,
            'total_installments' => 5,
            'paid_installments' => 0,
        ]);

        $payment = $loan->payments()->create([
            'due_date' => Carbon::parse('2026-03-25'),
            'actual_date' => Carbon::parse('2026-03-20'),
            'amount' => 200,
            'status' => LoanPaymentStatus::PAID,
        ]);

        $payment->update(['status' => LoanPaymentStatus::PENDING]);

        $this->assertDatabaseCount('transactions', 1);

        $posting = Transaction::withTrashed()->where('loan_payment_id', $payment->id)->first();
        $this->assertNotNull($posting?->deleted_at);

        $account->refresh();
        $this->assertSame(1000.0, (float) $account->balance);
    }

    /** @test */
    public function it_creates_a_transaction_for_due_pending_payments_when_synced(): void
    {
        Carbon::setTestNow('2026-03-26 12:00:00');

        $account = Account::factory()->create(['balance' => 1000]);

        $loan = Loan::factory()->create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'total_amount' => 1000,
            'monthly_payment' => 200,
            'remaining_amount' => 1000,
            'total_installments' => 5,
            'paid_installments' => 0,
        ]);

        $payment = $loan->payments()->create([
            'due_date' => Carbon::parse('2026-03-25'),
            'amount' => 200,
            'status' => LoanPaymentStatus::PENDING,
        ]);

        app(LoanPaymentPostingService::class)->syncDuePayments();

        $this->assertDatabaseHas('transactions', [
            'loan_payment_id' => $payment->id,
            'amount' => -200.00,
            'date' => '2026-03-25 00:00:00',
        ]);

        $account->refresh();
        $this->assertSame(800.0, (float) $account->balance);
    }
}
