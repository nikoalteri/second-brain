<?php

namespace Tests\Unit;

use App\Enums\LoanStatus;
use App\Enums\LoanPaymentStatus;
use App\Models\Loan;
use App\Services\LoanScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class LoanScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_generates_payments_for_a_loan()
    {
        $loan = Loan::factory()->create([
            'start_date' => Carbon::now()->subMonths(2),
            'total_installments' => 3,
            'withdrawal_day' => 15,
            'monthly_payment' => 100,
            'skip_weekends' => false,
        ]);

        $service = new LoanScheduleService();
        $service->generate($loan);

        $this->assertCount(3, $loan->payments);
        foreach ($loan->payments as $payment) {
            $this->assertEquals(100, $payment->amount);
            $this->assertEquals(LoanPaymentStatus::PENDING, $payment->status);
        }
    }

    /** @test */
    public function it_syncs_loan_status_and_amount()
    {
        $loan = Loan::factory()->create([
            'total_amount' => 300,
            'monthly_payment' => 100,
            'total_installments' => 3,
        ]);
        $service = new LoanScheduleService();
        $service->generate($loan);

        // Simula pagamento di una rata
        $payment = $loan->payments()->first();
        $payment->update(['status' => 'paid']);

        $service->syncLoan($loan->fresh());

        $loan->refresh();
        $this->assertEquals(1, $loan->paid_installments);
        $this->assertEquals(200, $loan->remaining_amount);
        $this->assertEquals(LoanStatus::ACTIVE, $loan->status);
    }
}
