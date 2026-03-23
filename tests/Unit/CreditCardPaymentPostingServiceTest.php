<?php

namespace Tests\Unit;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardPaymentPostingServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_posts_a_single_negative_transaction_when_credit_card_payment_is_paid(): void
    {
        $account = Account::factory()->create(['balance' => 1000]);

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta Test',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $card->payments()->create([
            'due_date' => Carbon::parse('2026-04-15'),
            'actual_date' => Carbon::parse('2026-04-15'),
            'installment_amount' => 250,
            'interest_amount' => 0,
            'principal_amount' => 250,
            'stamp_duty_amount' => 2,
            'total_amount' => 252,
            'status' => CreditCardPaymentStatus::PAID,
        ]);

        $this->assertDatabaseCount('transactions', 1);

        $transaction = Transaction::first();
        $this->assertSame(-252.0, (float) $transaction->amount);
        $this->assertSame($account->id, $transaction->account_id);

        $account->refresh();
        $this->assertSame(748.0, (float) $account->balance);
    }

    /** @test */
    public function it_does_not_duplicate_posting_on_credit_card_payment_updates(): void
    {
        $account = Account::factory()->create(['balance' => 1000]);

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Carta Test 2',
            'type' => CreditCardType::REVOLVING,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 1000,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $payment = $card->payments()->create([
            'due_date' => Carbon::parse('2026-04-15'),
            'actual_date' => Carbon::parse('2026-04-15'),
            'installment_amount' => 250,
            'interest_amount' => 10,
            'principal_amount' => 240,
            'stamp_duty_amount' => 2,
            'total_amount' => 252,
            'status' => CreditCardPaymentStatus::PAID,
        ]);

        $payment->update(['notes' => 'updated']);

        $this->assertDatabaseCount('transactions', 1);
    }
}
