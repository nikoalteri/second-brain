<?php

namespace Tests\Unit;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardType;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use App\Services\RevolvingCreditCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The daily balances of a cycle must depend only on events up to the end of that cycle:
 * later expenses and payments must not change the result of a historical recalculation.
 */
class CreditCardDailyBalanceHistoryTest extends TestCase
{
    use RefreshDatabase;

    private RevolvingCreditCalculator $calculator;

    private CreditCard $card;

    private CreditCardCycle $january;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new RevolvingCreditCalculator;

        $this->card = CreditCard::factory()->create([
            'type' => CreditCardType::REVOLVING,
            'interest_rate' => 14,
            'fixed_payment' => 250,
            'opening_balance' => 100,
        ]);

        $this->january = CreditCardCycle::factory()->create([
            'credit_card_id' => $this->card->id,
            'period_start_date' => Carbon::parse('2026-01-01'),
            'statement_date' => Carbon::parse('2026-01-31'),
            'total_spent' => 50,
        ]);

        CreditCardExpense::factory()->create([
            'credit_card_id' => $this->card->id,
            'credit_card_cycle_id' => $this->january->id,
            'spent_at' => Carbon::parse('2026-01-10'),
            'amount' => 50,
        ]);
    }

    private function januaryBalances(): array
    {
        return $this->calculator->calculateDailyBalances($this->january->fresh());
    }

    private function payPrincipal(string $date, float $amount, ?CreditCardCycle $cycle = null): void
    {
        CreditCardPayment::create([
            'credit_card_id' => $this->card->id,
            'credit_card_cycle_id' => ($cycle ?? $this->january)->id,
            'due_date' => Carbon::parse($date),
            'actual_date' => Carbon::parse($date),
            'installment_amount' => $amount,
            'interest_amount' => 0,
            'principal_amount' => $amount,
            'stamp_duty_amount' => 0,
            'total_amount' => $amount,
            'status' => CreditCardPaymentStatus::PAID,
        ]);
    }

    #[Test]
    public function january_balances_start_from_the_opening_balance(): void
    {
        $balances = $this->januaryBalances();

        $this->assertSame(100.0, $balances['2026-01-01']);
        $this->assertSame(150.0, $balances['2026-01-10']);
        $this->assertSame(150.0, $balances['2026-01-31']);
    }

    #[Test]
    public function an_expense_in_a_later_cycle_does_not_change_an_earlier_cycle(): void
    {
        $before = $this->januaryBalances();

        $february = CreditCardCycle::factory()->create([
            'credit_card_id' => $this->card->id,
            'period_start_date' => Carbon::parse('2026-02-01'),
            'statement_date' => Carbon::parse('2026-02-28'),
            'total_spent' => 20,
        ]);

        CreditCardExpense::factory()->create([
            'credit_card_id' => $this->card->id,
            'credit_card_cycle_id' => $february->id,
            'spent_at' => Carbon::parse('2026-02-05'),
            'amount' => 20,
        ]);

        $this->assertSame($before, $this->januaryBalances());
    }

    #[Test]
    public function a_principal_payment_after_the_cycle_does_not_change_it(): void
    {
        $before = $this->januaryBalances();

        $this->payPrincipal('2026-02-15', 80);

        $this->assertSame($before, $this->januaryBalances());
    }

    #[Test]
    public function a_payment_before_the_cycle_reduces_its_opening_balance(): void
    {
        $this->payPrincipal('2025-12-20', 40, null);

        $this->assertSame(60.0, $this->januaryBalances()['2026-01-01']);
    }

    #[Test]
    public function expenses_of_an_earlier_cycle_are_part_of_the_next_cycle_opening_balance(): void
    {
        $february = CreditCardCycle::factory()->create([
            'credit_card_id' => $this->card->id,
            'period_start_date' => Carbon::parse('2026-02-01'),
            'statement_date' => Carbon::parse('2026-02-28'),
            'total_spent' => 0,
        ]);

        $balances = $this->calculator->calculateDailyBalances($february->fresh());

        // opening balance 100 + January expense 50
        $this->assertSame(150.0, $balances['2026-02-01']);
    }
}
