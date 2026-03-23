<?php

namespace Tests\Unit;

use App\Enums\CreditCardCycleStatus;
use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use App\Services\CreditCardKpiService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardKpiServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_expected_credit_card_kpis_for_user(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-18'));

        $account = Account::factory()->create();

        $chargeCard = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Charge',
            'type' => CreditCardType::CHARGE,
            'credit_limit' => 1000,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $revolvingCard = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Revolving',
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 2000,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 860,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        CreditCardExpense::create([
            'credit_card_id' => $chargeCard->id,
            'spent_at' => Carbon::parse('2026-03-05'),
            'amount' => 120,
            'description' => 'Grocery',
        ]);

        CreditCardExpense::create([
            'credit_card_id' => $revolvingCard->id,
            'spent_at' => Carbon::parse('2026-03-09'),
            'amount' => 80,
            'description' => 'Fuel',
        ]);

        CreditCardExpense::create([
            'credit_card_id' => $revolvingCard->id,
            'spent_at' => Carbon::parse('2026-02-25'),
            'amount' => 40,
            'description' => 'Old month expense',
        ]);

        $overdueCycle = CreditCardCycle::create([
            'credit_card_id' => $chargeCard->id,
            'period_month' => '2026-02',
            'statement_date' => Carbon::parse('2026-02-28'),
            'due_date' => Carbon::parse('2026-03-10'),
            'total_spent' => 200,
            'total_due' => 210,
            'paid_amount' => 50,
            'status' => CreditCardCycleStatus::OVERDUE,
        ]);

        CreditCardPayment::create([
            'credit_card_id' => $revolvingCard->id,
            'credit_card_cycle_id' => null,
            'due_date' => Carbon::parse('2026-03-20'),
            'actual_date' => null,
            'installment_amount' => 250,
            'interest_amount' => 10,
            'principal_amount' => 240,
            'stamp_duty_amount' => 2,
            'total_amount' => 252,
            'status' => CreditCardPaymentStatus::PENDING,
        ]);

        CreditCardPayment::create([
            'credit_card_id' => $chargeCard->id,
            'credit_card_cycle_id' => $overdueCycle->id,
            'due_date' => Carbon::parse('2026-03-14'),
            'actual_date' => Carbon::parse('2026-03-14'),
            'installment_amount' => 100,
            'interest_amount' => 0,
            'principal_amount' => 100,
            'stamp_duty_amount' => 0,
            'total_amount' => 100,
            'status' => CreditCardPaymentStatus::PAID,
        ]);

        $kpis = app(CreditCardKpiService::class)->getForUser($account->user_id, Carbon::parse('2026-03-18'));

        $this->assertSame(200.0, $kpis['spent_this_month']);
        $this->assertSame(252.0, $kpis['next_due_amount']);
        $this->assertSame('2026-03-20', $kpis['next_due_date']?->toDateString());
        $this->assertSame(980.0, $kpis['revolving_residual']);
        $this->assertSame(1, $kpis['overdue_cycles_count']);
        $this->assertSame(110.0, $kpis['overdue_total_due']);
        $this->assertSame(2000.0, $kpis['total_available_limited']);
        $this->assertSame(0, $kpis['unlimited_cards_count']);

        Carbon::setTestNow();
    }
}
