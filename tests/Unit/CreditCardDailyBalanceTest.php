<?php

namespace Tests\Unit;

use App\Enums\CreditCardType;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Services\CreditCardCycleService;
use App\Services\RevolvingCreditCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardDailyBalanceTest extends TestCase
{
    use RefreshDatabase;
    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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
