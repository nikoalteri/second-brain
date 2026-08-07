<?php

namespace Tests\Unit;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardType;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use App\Services\CreditCardCycleService;
use App\Services\RevolvingCreditCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreditCardDailyBalanceTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function calculates_daily_balances_from_expenses(): void
    {
        $calculator = new RevolvingCreditCalculator();

        $card = CreditCard::factory()->create([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 14,
            'current_balance' => 100,
        ]);

        $startDate = Carbon::parse('2026-03-01');
        $endDate = Carbon::parse('2026-03-10');

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => $startDate,
            'statement_date' => $endDate,
            'total_spent' => 300,
        ]);

        // Create expenses on different dates
        CreditCardExpense::factory()->create([
            'credit_card_id' => $card->id,
            'credit_card_cycle_id' => $cycle->id,
            'spent_at' => Carbon::parse('2026-03-02'),
            'amount' => 100,
        ]);

        CreditCardExpense::factory()->create([
            'credit_card_id' => $card->id,
            'credit_card_cycle_id' => $cycle->id,
            'spent_at' => Carbon::parse('2026-03-05'),
            'amount' => 200,
        ]);

        $dailyBalances = $calculator->calculateDailyBalances($cycle);

        // Should have balances for each day from Mar 1 to Mar 10
        $this->assertCount(10, $dailyBalances);

        // Mar 1: starting balance 100
        $this->assertSame(100.0, $dailyBalances['2026-03-01']);

        // Mar 2: 100 + 100 (expense)
        $this->assertSame(200.0, $dailyBalances['2026-03-02']);

        // Mar 3-4: same as Mar 2
        $this->assertSame(200.0, $dailyBalances['2026-03-03']);
        $this->assertSame(200.0, $dailyBalances['2026-03-04']);

        // Mar 5: 200 + 200 (expense)
        $this->assertSame(400.0, $dailyBalances['2026-03-05']);

        // Mar 6-10: same as Mar 5
        for ($day = 6; $day <= 10; $day++) {
            $dateStr = sprintf('2026-03-%02d', $day);
            $this->assertSame(400.0, $dailyBalances[$dateStr]);
        }
    }

    #[Test]
    public function calculates_cycle_interest_from_daily_balances(): void
    {
        $calculator = new RevolvingCreditCalculator();

        // Simulate daily balances over 10 days
        $dailyBalances = [
            '2026-03-01' => 100.0,
            '2026-03-02' => 100.0,
            '2026-03-03' => 200.0,
            '2026-03-04' => 200.0,
            '2026-03-05' => 200.0,
            '2026-03-06' => 200.0,
            '2026-03-07' => 200.0,
            '2026-03-08' => 200.0,
            '2026-03-09' => 200.0,
            '2026-03-10' => 200.0,
        ];

        // 14% annual rate = 14% / 365 = 0.0384% per day
        // Total interest = (100*2 + 200*8) * (0.14/365)
        $interest = $calculator->calculateInterestFromDailyBalances($dailyBalances, 14.0);

        // (100 + 100 + 200 + 200 + 200 + 200 + 200 + 200 + 200 + 200) * (0.14/365)
        // = 1700 * 0.0003835... = 0.652055...
        $this->assertEqualsWithDelta(0.69, $interest, 0.01);
    }

    #[Test]
    public function calculates_payment_breakdown_from_cycle_with_daily_balance(): void
    {
        $calculator = new RevolvingCreditCalculator();

        $card = CreditCard::factory()->create([
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 14,
            'stamp_duty_amount' => 2,
            'current_balance' => 0,
        ]);

        $startDate = Carbon::parse('2026-03-01');
        $endDate = Carbon::parse('2026-03-20');

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => $startDate,
            'statement_date' => $endDate,
            'total_spent' => 540,
        ]);

        // Add one expense at the beginning
        CreditCardExpense::factory()->create([
            'credit_card_id' => $card->id,
            'credit_card_cycle_id' => $cycle->id,
            'spent_at' => $startDate,
            'amount' => 540,
        ]);

        $breakdown = $calculator->calculatePaymentBreakdown($cycle);

        $this->assertNotEmpty($breakdown);
        $this->assertIsNumeric($breakdown['interest_amount']);
        $this->assertIsNumeric($breakdown['principal_amount']);
        $this->assertSame(250.0, $breakdown['installment_amount']);
        $this->assertSame(2.0, $breakdown['stamp_duty_amount']);
        $this->assertSame(252.0, $breakdown['total_due']);
    }

    #[Test]
    public function mid_cycle_paid_payment_reduces_daily_balance_from_its_actual_date(): void
    {
        $calculator = new RevolvingCreditCalculator();

        $card = CreditCard::factory()->create([
            'type' => CreditCardType::REVOLVING,
            'interest_rate' => 14,
            'credit_limit' => 4000,
            'fixed_payment' => 250,
            'stamp_duty_amount' => 2,
        ]);

        // Expense belonging to an earlier cycle
        CreditCardExpense::factory()->create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2027-04-10'),
            'amount' => 1200.00,
        ]);

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => Carbon::parse('2027-05-07'),
            'statement_date' => Carbon::parse('2027-06-06'),
            'total_spent' => 0,
        ]);

        CreditCardPayment::create([
            'credit_card_id' => $card->id,
            'credit_card_cycle_id' => $cycle->id,
            'due_date' => Carbon::parse('2027-05-22'),
            'actual_date' => Carbon::parse('2027-05-22'),
            'installment_amount' => 217.00,
            'interest_amount' => 15.00,
            'principal_amount' => 200.00,
            'stamp_duty_amount' => 2.00,
            'total_amount' => 217.00,
            'status' => CreditCardPaymentStatus::PAID,
        ]);

        $card->update(['current_balance' => 1000.00]);
        $card->refresh();

        $dailyBalances = $calculator->calculateDailyBalances($cycle);

        $this->assertCount(31, $dailyBalances);
        $this->assertSame(1200.0, $dailyBalances['2027-05-21']);
        $this->assertSame(1000.0, $dailyBalances['2027-05-22']);
        $this->assertSame(1000.0, $dailyBalances['2027-06-06']);
        $this->assertSame((float) $card->current_balance, $dailyBalances['2027-06-06']);

        $interest = $calculator->calculateInterestFromDailyBalances($dailyBalances, 14.0);
        $this->assertEqualsWithDelta(13.04, $interest, 0.01);
    }

    #[Test]
    public function pending_payment_does_not_reduce_daily_balance(): void
    {
        $calculator = new RevolvingCreditCalculator();

        $card = CreditCard::factory()->create([
            'type' => CreditCardType::REVOLVING,
            'interest_rate' => 14,
            'credit_limit' => 4000,
            'fixed_payment' => 250,
            'stamp_duty_amount' => 2,
        ]);

        CreditCardExpense::factory()->create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2027-04-10'),
            'amount' => 1200.00,
        ]);

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => Carbon::parse('2027-05-07'),
            'statement_date' => Carbon::parse('2027-06-06'),
            'total_spent' => 0,
        ]);

        CreditCardPayment::create([
            'credit_card_id' => $card->id,
            'credit_card_cycle_id' => $cycle->id,
            'due_date' => Carbon::parse('2027-05-22'),
            'actual_date' => null,
            'installment_amount' => 217.00,
            'interest_amount' => 15.00,
            'principal_amount' => 200.00,
            'stamp_duty_amount' => 2.00,
            'total_amount' => 217.00,
            'status' => CreditCardPaymentStatus::PENDING,
        ]);

        $card->update(['current_balance' => 1200.00]);
        $card->refresh();

        $dailyBalances = $calculator->calculateDailyBalances($cycle);

        $this->assertCount(31, $dailyBalances);
        foreach ($dailyBalances as $balance) {
            $this->assertSame(1200.0, $balance);
        }

        $interest = $calculator->calculateInterestFromDailyBalances($dailyBalances, 14.0);
        $this->assertEqualsWithDelta(14.28, $interest, 0.01);
    }

    #[Test]
    public function daily_balance_interest_is_lower_than_direct_monthly_rate(): void
    {
        $calculator = new RevolvingCreditCalculator();

        // 20-day cycle with constant balance
        $dailyBalances = array_fill_keys(range(1, 20), 542.0);

        $dailyInterest = $calculator->calculateInterestFromDailyBalances($dailyBalances, 14.0);

        // Direct monthly: 542 * 0.14 = 75.88
        // Daily over 20 days: 542 * (0.14/365) * 20 = 542 * 0.00766... = 4.16
        // (Daily method accumulates interest slowly)
        $this->assertLessThan(75.88, $dailyInterest);
        $this->assertEqualsWithDelta(4.16, $dailyInterest, 0.01);
    }
}
