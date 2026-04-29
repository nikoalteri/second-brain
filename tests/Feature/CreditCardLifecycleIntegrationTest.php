<?php

namespace Tests\Feature;

use App\Enums\CreditCardCycleStatus;
use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Models\Transaction;
use App\Services\CreditCardCycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreditCardLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function charge_cycle_issue_and_payment_sync_everything(): void
    {
        $account = Account::factory()->create(['balance' => 1000]);

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta Saldo',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 0,
        ]);

        CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2026-03-10'),
            'amount' => 300,
            'description' => 'Expense before statement issue',
        ]);

        $cycle = CreditCardCycle::query()
            ->where('credit_card_id', $card->id)
            ->where('period_month', '2026-03')
            ->firstOrFail();

        $issued = app(CreditCardCycleService::class)->issueCycle($cycle);

        $this->assertTrue($issued);

        $cycle->refresh();
        $card->refresh();

        $this->assertSame(CreditCardCycleStatus::ISSUED, $cycle->status);
        $this->assertSame(300.0, (float) $cycle->total_due);
        $this->assertSame(300.0, (float) $card->current_balance);

        $payment = $cycle->payments()->first();
        $payment->update([
            'status' => CreditCardPaymentStatus::PAID,
            'actual_date' => Carbon::parse('2026-04-15'),
        ]);

        $cycle->refresh();
        $card->refresh();
        $account->refresh();

        $this->assertSame(CreditCardCycleStatus::PAID, $cycle->status);
        $this->assertSame(300.0, (float) $cycle->paid_amount);
        $this->assertSame(0.0, (float) $card->current_balance);
        $this->assertSame(700.0, (float) $account->balance);

        $posting = Transaction::query()->where('credit_card_payment_id', $payment->id)->first();
        $this->assertNotNull($posting);
        $this->assertSame(-300.0, (float) $posting->amount);
    }

    #[Test]
    public function revolving_issue_and_payment_reduce_residual_balance_by_principal(): void
    {
        $account = Account::factory()->create(['balance' => 1000]);

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta Revolving',
            'type' => CreditCardType::REVOLVING,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 1000,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'interest_calculation_method' => 'direct_monthly',
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2026-03-10'),
            'amount' => 100,
            'description' => 'Expense increases used credit immediately',
        ]);

        $cycle = CreditCardCycle::query()
            ->where('credit_card_id', $card->id)
            ->where('period_month', '2026-03')
            ->firstOrFail();

        $issued = app(CreditCardCycleService::class)->issueCycle($cycle);
        $this->assertTrue($issued);

        $cycle->refresh();
        $card->refresh();

        // Used balance already includes expenses: 1000 + 100 = 1100, interest 132 (1100*12%), principal 118 (250-132), total due 252.
        $this->assertSame(1100.0, (float) $card->current_balance);
        $this->assertSame(132.0, (float) $cycle->interest_amount);
        $this->assertSame(118.0, (float) $cycle->principal_amount);
        $this->assertSame(252.0, (float) $cycle->total_due);

        $payment = $cycle->payments()->first();
        $payment->update([
            'status' => CreditCardPaymentStatus::PAID,
            'actual_date' => Carbon::parse('2026-04-15'),
        ]);

        $cycle->refresh();
        $card->refresh();
        $account->refresh();

        $this->assertSame(CreditCardCycleStatus::PAID, $cycle->status);
        $this->assertSame(982.0, (float) $card->current_balance);
        $this->assertSame(748.0, (float) $account->balance);
    }

    #[Test]
    public function adding_expense_after_issue_is_rejected_and_preserves_statement(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-20'));

        $account = Account::factory()->create(['balance' => 1000]);

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta aggiorna emesso',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2026-03-10'),
            'amount' => 300,
            'description' => 'Initial expense',
        ]);

        $cycle = CreditCardCycle::query()
            ->where('credit_card_id', $card->id)
            ->where('period_month', '2026-03')
            ->firstOrFail();

        $this->assertTrue(app(CreditCardCycleService::class)->issueCycle($cycle));

        $this->expectException(ValidationException::class);

        try {
            CreditCardExpense::create([
                'credit_card_id' => $card->id,
                'spent_at' => Carbon::parse('2026-03-12'),
                'amount' => 50,
                'description' => 'Late expense on issued cycle',
            ]);
        } finally {
            $cycle->refresh();
            $payment = $cycle->payments()->firstOrFail();

            $this->assertSame(CreditCardCycleStatus::ISSUED, $cycle->status);
            $this->assertSame(300.0, (float) $cycle->total_spent);
            $this->assertSame(302.0, (float) $cycle->total_due);
            $this->assertSame(302.0, (float) $payment->total_amount);
            $this->assertSame(300.0, (float) $payment->principal_amount);
            $this->assertSame(2.0, (float) $payment->stamp_duty_amount);
        }

        Carbon::setTestNow();
    }

    #[Test]
    public function scheduled_command_creates_month_cycle_for_active_cards(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta Comando',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 15,
            'due_day' => 10,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $this->artisan('credit-cards:generate-cycles --month=2026-03')
            ->assertExitCode(0);

        $this->assertDatabaseHas('credit_card_cycles', [
            'credit_card_id' => $card->id,
            'period_month' => '2026-03',
        ]);
    }

    #[Test]
    public function cycle_without_due_date_does_not_become_overdue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-20'));

        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta senza scadenza',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 15,
            'due_day' => null,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $cycle = CreditCardCycle::create([
            'credit_card_id' => $card->id,
            'period_month' => '2026-04',
            'statement_date' => Carbon::parse('2026-04-15'),
            'due_date' => null,
            'total_spent' => 150,
            'status' => CreditCardCycleStatus::OPEN,
        ]);

        $issued = app(CreditCardCycleService::class)->issueCycle($cycle);
        $this->assertTrue($issued);

        $cycle->refresh();
        $this->assertNull($cycle->due_date);
        $this->assertSame(CreditCardCycleStatus::ISSUED, $cycle->status);

        app(CreditCardCycleService::class)->refreshCycleStatuses($card->fresh(['cycles.payments']));

        $cycle->refresh();
        $this->assertSame(CreditCardCycleStatus::ISSUED, $cycle->status);

        Carbon::setTestNow();
    }

    #[Test]
    public function expense_is_assigned_to_existing_custom_range_cycle(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta range personalizzato',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 6,
            'due_day' => 19,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $cycle = CreditCardCycle::create([
            'credit_card_id' => $card->id,
            'period_month' => '2026-03',
            'period_start_date' => Carbon::parse('2026-03-06'),
            'statement_date' => Carbon::parse('2026-04-05'),
            'due_date' => Carbon::parse('2026-04-19'),
            'total_spent' => 0,
            'status' => CreditCardCycleStatus::OPEN,
        ]);

        $expense = CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2026-04-02'),
            'amount' => 120,
            'description' => 'Spesa dentro range custom',
        ]);

        $expense->refresh();
        $cycle->refresh();

        $this->assertSame($cycle->id, (int) $expense->credit_card_cycle_id);
        $this->assertSame(120.0, (float) $cycle->total_spent);
    }

    #[Test]
    public function same_month_allows_multiple_cycles_when_date_ranges_differ(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta multi range',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 6,
            'due_day' => 19,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        CreditCardCycle::create([
            'credit_card_id' => $card->id,
            'period_month' => '2026-03',
            'period_start_date' => Carbon::parse('2026-03-01'),
            'statement_date' => Carbon::parse('2026-03-15'),
            'due_date' => Carbon::parse('2026-03-19'),
            'total_spent' => 0,
            'status' => CreditCardCycleStatus::OPEN,
        ]);

        CreditCardCycle::create([
            'credit_card_id' => $card->id,
            'period_month' => '2026-03',
            'period_start_date' => Carbon::parse('2026-03-16'),
            'statement_date' => Carbon::parse('2026-03-31'),
            'due_date' => Carbon::parse('2026-04-19'),
            'total_spent' => 0,
            'status' => CreditCardCycleStatus::OPEN,
        ]);

        $this->assertSame(2, CreditCardCycle::query()->where('credit_card_id', $card->id)->count());
    }
}
