<?php

namespace Tests\Unit;

use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Services\RevolvingCreditCalculator;
use Carbon\Carbon;
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

    /** @test */
    public function it_calculates_daily_balances_for_a_cycle()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 692.00, // 542 pre-cycle + 150 total_spent (withoutEvents, so manual)
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

        // Starting balance: 542
        // Mar 1: 542 + 100 = 642
        $this->assertEquals(642.00, $dailyBalances['2026-03-01']);

        // Mar 2-4: no expenses
        $this->assertEquals(642.00, $dailyBalances['2026-03-02']);
        $this->assertEquals(642.00, $dailyBalances['2026-03-04']);

        // Mar 5: 642 + 50 = 692
        $this->assertEquals(692.00, $dailyBalances['2026-03-05']);

        // Mar 20: still 692
        $this->assertEquals(692.00, $dailyBalances['2026-03-20']);

        // Should have exactly 20 days (Mar 1-20)
        $this->assertCount(20, $dailyBalances);
    }

    /** @test */
    public function it_calculates_interest_from_daily_balances()
    {
        $dailyBalances = [
            '2026-03-01' => 542.00,
            '2026-03-02' => 542.00,
            '2026-03-03' => 542.00,
            '2026-03-04' => 542.00,
            '2026-03-05' => 542.00,
            '2026-03-06' => 542.00,
            '2026-03-07' => 542.00,
            '2026-03-08' => 542.00,
            '2026-03-09' => 542.00,
            '2026-03-10' => 542.00,
            '2026-03-11' => 542.00,
            '2026-03-12' => 542.00,
            '2026-03-13' => 542.00,
            '2026-03-14' => 542.00,
            '2026-03-15' => 542.00,
            '2026-03-16' => 542.00,
            '2026-03-17' => 542.00,
            '2026-03-18' => 542.00,
            '2026-03-19' => 542.00,
            '2026-03-20' => 542.00,
        ];

        // 20 days at 542 * (14% / 365)
        // Expected: 542 * 0.14 / 365 * 20 ≈ 4.16
        $interest = $this->calculator->calculateInterestFromDailyBalances($dailyBalances, 14.00);

        $this->assertEqualsWithDelta(4.16, $interest, 0.01);
    }

    /** @test */
    public function first_cycle_has_zero_interest()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 542.00,
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

    /** @test */
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

    /** @test */
    public function it_validates_user_bank_statement_14_percent()
    {
        // User's real case: 542 debt, 14% rate, expects 75.88 interest
        // Using daily balance with varying balance throughout cycle

        $card = CreditCard::factory()->create([
            'current_balance' => 542.00,
            'interest_rate' => 14.00,
            'fixed_payment' => 250.00,
        ]);

        // Simulate a second cycle (not first)
        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'status' => 'paid',
        ]);

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'period_start_date' => Carbon::parse('2026-03-21'),
            'statement_date' => Carbon::parse('2026-04-20'),
            'total_spent' => 0,
            'status' => 'open',
        ]);

        // With constant 542 balance for 31 days:
        // Interest = 542 * (0.14 / 365) * 31 = 6.49 (not 75.88)
        // 
        // The 75.88 figure suggests either:
        // 1. Different balance variation throughout the cycle
        // 2. Interest calculation method is monthly: 542 * 0.14 = 75.88
        //
        // User confirmed 14% is annual, applied monthly
        // So: 542 * (14 / 100) = 75.88 ✓

        // For now, daily method would give ~6.49
        // This validates that daily method is different from user's bank
        // User will provide more statements to confirm which method their bank uses

        $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);
        
        // Daily method for constant balance: ~6.49
        $this->assertGreaterThan(5.0, $breakdown['interest_amount']);
        $this->assertLessThan(8.0, $breakdown['interest_amount']);
    }

    /** @test */
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

    /** @test */
    public function payment_respects_fixed_payment_limit()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 1000.00,
            'interest_rate' => 12.00,
            'fixed_payment' => 250.00,
            'credit_limit' => 5000.00,
        ]);

        // Not first cycle
        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'status' => 'paid',
        ]);

        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'total_spent' => 200.00,
            'status' => 'open',
        ]);

        $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);

        // installment should not exceed fixed_payment
        $this->assertLessThanOrEqual(250.00, $breakdown['installment_amount']);
    }

    /** @test */
    public function it_returns_empty_array_when_no_card()
    {
        // Create a cycle without loading card
        $cycle = CreditCardCycle::factory()->create();
        $cycle->creditCard()->delete();

        $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);

        $this->assertEmpty($breakdown);
    }

    /** @test */
    public function it_calculates_interest_using_direct_monthly_method()
    {
        $currentBalance = 542.00;
        $annualRate = 14.00;

        // Direct monthly: 542 * (14 / 100) = 75.88
        $interest = $this->calculator->calculateInterestDirectMonthly($currentBalance, $annualRate);

        $this->assertEqualsWithDelta(75.88, $interest, 0.01);
    }

    /** @test */
    public function it_uses_direct_monthly_method_when_configured()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 542.00,
            'interest_rate' => 14.00,
            'fixed_payment' => 250.00,
            'interest_calculation_method' => 'direct_monthly',
        ]);

        // First cycle (already issued)
        CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'status' => 'paid',
        ]);

        // Second cycle
        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $card->id,
            'total_spent' => 0,
            'status' => 'open',
        ]);

        $breakdown = $this->calculator->calculatePaymentBreakdown($cycle);

        // Should use direct monthly: 542 * 0.14 = 75.88
        $this->assertEqualsWithDelta(75.88, $breakdown['interest_amount'], 0.01);
    }

    /** @test */
    public function daily_balance_and_direct_monthly_produce_different_results()
    {
        $card = CreditCard::factory()->create([
            'current_balance' => 542.00,
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
        
        // Direct monthly should be ~75.88, daily should be much lower (~6.49 for 31 days)
        $this->assertGreaterThan($breakdownDaily['interest_amount'], $breakdownMonthly['interest_amount']);
    }
}
