<?php

namespace Tests\Feature;

use App\Enums\CreditCardCycleStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreditCardExpenseIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function creating_expense_assigns_cycle_and_updates_total_spent(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta Spese',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $expense = CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2026-03-10'),
            'amount' => 120,
            'description' => 'Spesa supermercato',
        ]);

        $expense->refresh();

        $this->assertNotNull($expense->credit_card_cycle_id);

        $cycle = CreditCardCycle::findOrFail($expense->credit_card_cycle_id);
        $card->refresh();

        $this->assertSame('2026-03', $cycle->period_month);
        $this->assertSame(120.0, (float) $cycle->total_spent);
        $this->assertSame(120.0, (float) $card->current_balance);
    }

    /** @test */
    public function deleting_expense_recomputes_cycle_total_spent(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta Spese 2',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $cycle = CreditCardCycle::create([
            'credit_card_id' => $card->id,
            'period_month' => '2026-03',
            'statement_date' => Carbon::parse('2026-03-31'),
            'due_date' => Carbon::parse('2026-04-15'),
            'total_spent' => 0,
            'status' => CreditCardCycleStatus::OPEN,
        ]);

        $expense1 = CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'credit_card_cycle_id' => $cycle->id,
            'spent_at' => Carbon::parse('2026-03-10'),
            'amount' => 80,
            'description' => 'Carburante',
        ]);

        $expense2 = CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'credit_card_cycle_id' => $cycle->id,
            'spent_at' => Carbon::parse('2026-03-11'),
            'amount' => 40,
            'description' => 'Farmacia',
        ]);

        $cycle->refresh();
        $this->assertSame(120.0, (float) $cycle->total_spent);

        $expense2->delete();

        $cycle->refresh();
        $card->refresh();
        $this->assertSame(80.0, (float) $cycle->total_spent);
        $this->assertSame(80.0, (float) $card->current_balance);

        $expense1->delete();

        $cycle->refresh();
        $card->refresh();
        $this->assertSame(0.0, (float) $cycle->total_spent);
        $this->assertSame(0.0, (float) $card->current_balance);
    }

    /** @test */
    public function creating_expense_over_limit_is_blocked(): void
    {
        $account = Account::factory()->create();

        $limitedCard = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta con limite',
            'type' => CreditCardType::CHARGE,
            'credit_limit' => 100,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 90,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $this->expectException(ValidationException::class);

        try {
            CreditCardExpense::create([
                'credit_card_id' => $limitedCard->id,
                'spent_at' => Carbon::parse('2026-03-10'),
                'amount' => 20,
                'description' => 'Over limit expense',
            ]);
        } finally {
            $limitedCard->refresh();
            $this->assertSame(90.0, (float) $limitedCard->current_balance);
        }
    }

    /** @test */
    public function creating_expense_on_unlimited_card_is_allowed(): void
    {
        $account = Account::factory()->create();

        $unlimitedCard = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta illimitata',
            'type' => CreditCardType::CHARGE,
            'credit_limit' => null,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 90,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        CreditCardExpense::create([
            'credit_card_id' => $unlimitedCard->id,
            'spent_at' => Carbon::parse('2026-03-11'),
            'amount' => 200,
            'description' => 'Allowed unlimited expense',
        ]);

        $unlimitedCard->refresh();
        $this->assertSame(290.0, (float) $unlimitedCard->current_balance);
    }
}
