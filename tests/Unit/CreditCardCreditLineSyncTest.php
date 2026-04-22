<?php

namespace Tests\Unit;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardExpense;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardCreditLineSyncTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function expense_create_update_delete_syncs_used_credit_with_deltas(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Delta card',
            'type' => CreditCardType::CHARGE,
            'credit_limit' => 1000,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 100,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $expense = CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2026-03-10'),
            'amount' => 50,
            'description' => 'Initial expense',
        ]);

        $card->refresh();
        $this->assertSame(150.0, (float) $card->current_balance);

        $expense->update(['amount' => 80]);

        $card->refresh();
        $this->assertSame(180.0, (float) $card->current_balance);

        $expense->delete();

        $card->refresh();
        $this->assertSame(100.0, (float) $card->current_balance);
    }

    #[Test]
    public function payments_reintegrate_only_principal_on_status_changes(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Principal card',
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 2000,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 500,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $payment = $card->payments()->create([
            'due_date' => Carbon::parse('2026-04-15'),
            'actual_date' => null,
            'installment_amount' => 250,
            'interest_amount' => 10,
            'principal_amount' => 240,
            'stamp_duty_amount' => 2,
            'total_amount' => 252,
            'status' => CreditCardPaymentStatus::PENDING,
        ]);

        $card->refresh();
        $this->assertSame(500.0, (float) $card->current_balance);

        $payment->update([
            'status' => CreditCardPaymentStatus::PAID,
            'actual_date' => Carbon::parse('2026-04-15'),
        ]);

        $card->refresh();
        $this->assertSame(260.0, (float) $card->current_balance);

        $payment->update([
            'status' => CreditCardPaymentStatus::PENDING,
            'actual_date' => null,
        ]);

        $card->refresh();
        $this->assertSame(500.0, (float) $card->current_balance);
    }
}
