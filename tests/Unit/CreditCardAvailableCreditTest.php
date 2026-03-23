<?php

namespace Tests\Unit;

use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditCardAvailableCreditTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_returns_null_available_credit_for_unlimited_cards(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Unlimited card',
            'type' => CreditCardType::CHARGE,
            'credit_limit' => null,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 120,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $this->assertTrue($card->is_unlimited);
        $this->assertNull($card->available_credit);
    }

    /** @test */
    public function it_calculates_available_credit_for_limited_cards(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Limited card',
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 1000,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 320,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $this->assertFalse($card->is_unlimited);
        $this->assertSame(680.0, $card->available_credit);
    }

    /** @test */
    public function it_clamps_available_credit_to_zero_when_used_credit_exceeds_limit(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Over limit card',
            'type' => CreditCardType::CHARGE,
            'credit_limit' => 500,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 750,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $this->assertSame(0.0, $card->available_credit);
    }
}
