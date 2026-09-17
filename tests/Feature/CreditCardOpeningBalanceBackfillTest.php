<?php

namespace Tests\Feature;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardExpense;
use App\Services\CreditCardCycleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreditCardOpeningBalanceBackfillTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function pre_existing_card_balance_is_stable_across_repeated_recomputes(): void
    {
        $account = Account::factory()->create();

        // Simulate a genuinely pre-existing row: inserted directly, bypassing Eloquent's
        // `creating` hook, so opening_balance sits at the migration's schema default (0) —
        // exactly what every real card in the database looks like immediately after this
        // phase's migration runs.
        $cardId = DB::table('credit_cards')->insertGetId([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Pre-existing card',
            'type' => CreditCardType::REVOLVING->value,
            'credit_limit' => 2000,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 300,
            'opening_balance' => 0,
            'status' => CreditCardStatus::ACTIVE->value,
            'stamp_duty_amount' => 2,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $card = CreditCard::find($cardId);

        CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'spent_at' => Carbon::parse('2026-03-01'),
            'amount' => 300,
            'description' => 'Matches the pre-existing current_balance exactly',
        ]);

        $service = app(CreditCardCycleService::class);

        $service->syncCardBalance($card->fresh());
        $this->assertSame(300.0, (float) $card->fresh()->current_balance);

        // Idempotent: a second nightly-job-style run must not drift the value further.
        $service->syncCardBalance($card->fresh());
        $this->assertSame(300.0, (float) $card->fresh()->current_balance);

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

        $this->assertSame(60.0, (float) $card->fresh()->current_balance);
    }
}
