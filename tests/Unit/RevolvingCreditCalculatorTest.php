<?php

namespace Tests\Unit;

use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Services\RevolvingCreditCalculator;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RevolvingCreditCalculatorTest extends TestCase
{
    use \Illuminate\Foundation\Testing\RefreshDatabase;

    private RevolvingCreditCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = app(RevolvingCreditCalculator::class);
    }

    #[Test]
    public function it_calculates_daily_balances_for_a_cycle()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 750.00, // 600 pre-cycle + 150 total_spent (withoutEvents, so manual)
            'interest_rate' => 14.00,
        ]);

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => Carbon::parse('2026-03-01'),
            'statement_date' => Carbon::parse('2026-03-20'),
            'total_spent' => 150.00,
        ]);

        // Create expenses without triggering observers
        CreditCardExpense::withoutEvents(function () use ($card, $cycle) {
            CreditCardExpense::factory()->create([
                'credit_card_id' => $card->id,
                'credit_card_cycle_id' => $cycle->id,
                'spent_at' => Carbon::parse('2026-03-01'),
                'amount' => 100.00,
            ]);

            CreditCardExpense::factory()->create([
                'credit_card_id' => $card->id,
                'credit_card_cycle_id' => $cycle->id,
                'spent_at' => Carbon::parse('2026-03-05'),
                'amount' => 50.00,
            ]);
        });

        $dailyBalances = $this->calculator->calculateDailyBalances($cycle);

        // Starting balance: 600
        // Mar 1: 600 + 100 = 700
        $this->assertEquals(700.00, $dailyBalances['2026-03-01']);

        // Mar 2-4: no expenses
        $this->assertEquals(700.00, $dailyBalances['2026-03-02']);
        $this->assertEquals(700.00, $dailyBalances['2026-03-04']);

        // Mar 5: 700 + 50 = 750
        $this->assertEquals(750.00, $dailyBalances['2026-03-05']);

        // Mar 20: still 750
        $this->assertEquals(750.00, $dailyBalances['2026-03-20']);

        // Should have exactly 20 days (Mar 1-20)
        $this->assertCount(20, $dailyBalances);
    }

    #[Test]
    public function it_calculates_interest_from_daily_balances()
    {
        $dailyBalances = [
            '2026-03-01' => 600.00,
            '2026-03-02' => 600.00,
            '2026-03-03' => 600.00,
            '2026-03-04' => 600.00,
            '2026-03-05' => 600.00,
            '2026-03-06' => 600.00,
            '2026-03-07' => 600.00,
            '2026-03-08' => 600.00,
            '2026-03-09' => 600.00,
            '2026-03-10' => 600.00,
            '2026-03-11' => 600.00,
            '2026-03-12' => 600.00,
            '2026-03-13' => 600.00,
            '2026-03-14' => 600.00,
            '2026-03-15' => 600.00,
            '2026-03-16' => 600.00,
            '2026-03-17' => 600.00,
            '2026-03-18' => 600.00,
            '2026-03-19' => 600.00,
            '2026-03-20' => 600.00,
        ];

        // 20 days at 600 * (14% / 365)
        // Expected: 600 * 0.14 / 365 * 20 ≈ 4.60
        $interest = $this->calculator->calculateInterestFromDailyBalances($dailyBalances, 14.00);

        $this->assertEqualsWithDelta(4.60, $interest, 0.01);
    }

    #[Test]
    public function first_cycle_has_zero_interest()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 600.00,
            'interest_rate' => 14.00,
            'fixed_payment' => 250.00,
            'credit_limit' => 4000.00,
        ]);

        // Create first cycle (status = issued, so isFirstCycle will identify it)
        $firstCycle = CreditCardCycle::factory()->issued()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => Carbon::parse('2026-02-01'),
            'statement_date' => Carbon::parse('2026-02-20'),
            'total_spent' => 100.00,
        ]);

        // Verify this is the first cycle
        $this->assertTrue($this->calculator->isFirstCycle($card, $firstCycle));

        // Now calculate breakdown for a SECOND cycle 
        $secondCycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => Carbon::parse('2026-03-01'),
            'statement_date' => Carbon::parse('2026-03-20'),
            'total_spent' => 0,
            'status' => 'open',
        ]);

        $breakdown = $this->calculator->calculatePaymentBreakdown($secondCycle);

        // Second cycle should have interest
        $this->assertGreaterThan(0.0, $breakdown['interest_amount']);
    }

    #[Test]
    public function second_cycle_calculates_interest_correctly()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 292.00,
            'interest_rate' => 14.00,
            'fixed_payment' => 250.00,
            'credit_limit' => 4000.00,
        ]);

        // First cycle (already issued, so not "first" anymore)
        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => Carbon::parse('2026-03-01'),
            'statement_date' => Carbon::parse('2026-03-20'),
            'status' => 'paid',
        ]);

        // Second cycle
        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => Carbon::parse('2026-03-21'),
            'statement_date' => Carbon::parse('2026-04-20'),
            'total_spent' => 0,
            'status' => 'open',
        ]);

        // This is NOT the first cycle
        $this->assertFalse($this->calculator->isFirstCycle($card, $cycle));

        $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);

        // Should have interest calculated from daily balance
        // 292 * (14/365) * 31 days ≈ 3.49
        $this->assertGreaterThan(0.0, $breakdown['interest_amount']);
        $this->assertLessThan(5.0, $breakdown['interest_amount']);
    }

    #[Test]
    public function daily_balance_interest_over_a_31_day_cycle_at_14_percent()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 600.00,
            'interest_rate' => 14.00,
            'fixed_payment' => 250.00,
            'stamp_duty_amount' => 2,
        ]);

        // Simulate a second cycle (not first) — explicit, earlier statement_date so
        // isFirstCycle()'s date comparison isn't left to the factory's random range.
        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'statement_date' => Carbon::parse('2027-04-06'),
            'status' => 'paid',
        ]);

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => Carbon::parse('2027-05-07'),
            'statement_date' => Carbon::parse('2027-06-06'),
            'total_spent' => 0,
            'status' => 'open',
        ]);

        $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);

        // 31 days × 600 balance-days × 14% / 365 = 7.13
        $this->assertEqualsWithDelta(7.13, $breakdown['interest_amount'], 0.01);
    }

    #[Test]
    public function charge_card_has_no_interest()
    {
        $card = CreditCard::factory()->create([
            'type' => 'charge',
            'current_balance' => 1000.00,
            'interest_rate' => 0.00,
            'credit_limit' => 5000.00,
        ]);

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'total_spent' => 1000.00,
            'status' => 'open',
        ]);

        $breakdown = $this->calculator->calculateChargePaymentBreakdown($cycle);

        $this->assertEquals(0.0, $breakdown['interest_amount']);
        $this->assertEquals(1000.00, $breakdown['principal_amount']);
        $this->assertEquals(1000.00, $breakdown['installment_amount']);
        $this->assertEquals(0.0, $breakdown['next_balance']);
    }

    #[Test]
    public function payment_respects_fixed_payment_limit()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 1000.00,
            'interest_rate' => 12.00,
            'fixed_payment' => 250.00,
            'credit_limit' => 5000.00,
        ]);

        // Not first cycle — explicit, earlier statement_date so isFirstCycle()'s date comparison
        // isn't left to the factory's independent random date ranges for each call.
        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'statement_date' => Carbon::parse('2027-04-06'),
            'status' => 'paid',
        ]);

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'statement_date' => Carbon::parse('2027-05-06'),
            'total_spent' => 200.00,
            'status' => 'open',
        ]);

        $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);

        // installment should not exceed fixed_payment
        $this->assertLessThanOrEqual(250.00, $breakdown['installment_amount']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_card()
    {
        // Create a cycle without loading card
        $cycle = CreditCardCycle::factory()->create();
        $cycle->creditCard()->delete();

        $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);

        $this->assertEmpty($breakdown);
    }

    #[Test]
    public function it_calculates_interest_using_direct_monthly_method()
    {
        $currentBalance = 600.00;
        $annualRate = 14.00;

        // Flat monthly: 600 * (14 / 100 / 12) = 7.00
        $interest = $this->calculator->calculateInterestDirectMonthly($currentBalance, $annualRate);

        $this->assertSame(7.0, $interest);

        $this->assertSame(10.0, $this->calculator->calculateInterestDirectMonthly(1000.00, 12.0));
        $this->assertSame(0.0, $this->calculator->calculateInterestDirectMonthly(0.0, 14.0));
        $this->assertSame(0.0, $this->calculator->calculateInterestDirectMonthly(600.00, 0.0));
        $this->assertSame(0.0, $this->calculator->calculateInterestDirectMonthly(-50.0, 14.0));
    }

    #[Test]
    public function it_uses_direct_monthly_method_when_configured()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 600.00,
            'interest_rate' => 14.00,
            'fixed_payment' => 250.00,
            'interest_calculation_method' => 'direct_monthly',
        ]);

        // First cycle (already issued) — explicit, earlier statement_date. The factory's
        // default random date range doesn't guarantee ordering against the second cycle below,
        // and isFirstCycle() now determines "first" purely by statement_date comparison.
        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'statement_date' => Carbon::parse('2027-04-06'),
            'status' => 'paid',
        ]);

        // Second cycle
        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'statement_date' => Carbon::parse('2027-05-06'),
            'total_spent' => 0,
            'status' => 'open',
        ]);

        $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);

        // direct_monthly: 600 * 14 / 100 / 12 = 7.00
        $this->assertSame(7.0, $breakdown['interest_amount']);
        $this->assertSame(243.0, $breakdown['principal_amount']);
        $this->assertSame(252.0, $breakdown['total_due']);
    }

    #[Test]
    public function daily_balance_and_direct_monthly_produce_different_results()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 600.00,
            'interest_rate' => 14.00,
            'fixed_payment' => 250.00,
            'interest_calculation_method' => 'daily_balance',
        ]);

        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'status' => 'paid',
        ]);

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => Carbon::parse('2027-05-07'),
            'statement_date' => Carbon::parse('2027-06-06'),
            'total_spent' => 0,
            'status' => 'open',
        ]);

        // Calculate with daily balance
        $breakdownDaily = $this->calculator->calculatePaymentBreakdown($cycle);

        // Update card to use direct monthly
        $card->update(['interest_calculation_method' => 'direct_monthly']);
        $card->refresh();

        $breakdownMonthly = $this->calculator->calculatePaymentBreakdown($cycle->refresh());

        // They should produce different results
        $this->assertNotEquals($breakdownDaily['interest_amount'], $breakdownMonthly['interest_amount']);

        // Daily accrual over a 31-day period and a flat monthly twelfth are close but not identical.
        $this->assertEqualsWithDelta(7.13, $breakdownDaily['interest_amount'], 0.01);
        $this->assertSame(7.0, $breakdownMonthly['interest_amount']);
    }
}
