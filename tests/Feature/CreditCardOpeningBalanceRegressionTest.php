<?php

namespace Tests\Feature;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression coverage for findings from two rounds of Phase 21 code review, all reproduced
 * against the working tree before being fixed:
 * 1. The Filament create form always submits opening_balance explicitly (it's a required
 *    field defaulting to 0), so the original creating-hook bridge — which only fired when the
 *    key was entirely absent — silently dropped a manually-entered current_balance.
 * 1b. (Round 2) The mirror-image gap: creating with only opening_balance set left
 *    current_balance (and available_credit) at its stale 0 default until the next unrelated
 *    sync — confirmed by a scenario where a $900 opening balance against a $1000 limit still
 *    showed $1000 available immediately after creation.
 * 2. (Covered in CreditCardBalanceAuditCommandTest, not here — a report-criterion fix, not a
 *    balance-correctness one.)
 * 3. Updating opening_balance on an existing card left current_balance stale until the next
 *    unrelated payment/nightly sync.
 * 4. Both Store/UpdateCreditCardRequest allowed an explicit null for opening_balance, which
 *    then hit the NOT NULL database constraint instead of failing validation.
 */
class CreditCardOpeningBalanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function creating_with_both_fields_explicit_like_filament_still_seeds_opening_balance_from_current_balance(): void
    {
        $account = Account::factory()->create();

        // Mirrors exactly what CreateCreditCard::mutateFormDataBeforeCreate() hands to
        // CreditCard::create(): both current_balance and opening_balance present, the latter
        // at the form's untouched default of 0.
        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Filament-path card',
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 2000,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 500,
            'opening_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $this->assertSame(500.0, (float) $card->fresh()->opening_balance);

        // The real-world proof: a subsequent recompute (payment sync / nightly job) must not
        // zero the balance out.
        $card->payments()->create([
            'due_date' => now()->addMonth(),
            'actual_date' => null,
            'installment_amount' => 250,
            'interest_amount' => 10,
            'principal_amount' => 240,
            'stamp_duty_amount' => 2,
            'total_amount' => 252,
            'status' => CreditCardPaymentStatus::PENDING,
        ]);

        $this->assertSame(500.0, (float) $card->fresh()->current_balance);
    }

    #[Test]
    public function creating_with_only_opening_balance_set_initializes_current_balance_and_available_credit_immediately(): void
    {
        $account = Account::factory()->create();

        // Second review round, finding 1: opening_balance filled in, current_balance left at
        // its own default of 0 — the mirror-image gap of the Filament-path case above. Before
        // the fix, current_balance stayed 0 (available_credit wrongly showed the full limit)
        // until the next unrelated payment/nightly sync.
        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Opening-balance-only card',
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 1000,
            'fixed_payment' => 250,
            'interest_rate' => 12,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'opening_balance' => 900,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $fresh = $card->fresh();
        $this->assertSame(900.0, (float) $fresh->current_balance);
        $this->assertSame(100.0, (float) $fresh->available_credit);
    }

    #[Test]
    public function updating_opening_balance_recomputes_current_balance_immediately(): void
    {
        $account = Account::factory()->create();

        $card = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Update path card',
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

        $this->assertSame(500.0, (float) $card->fresh()->current_balance);

        $card->update(['opening_balance' => 700]);

        $this->assertSame(700.0, (float) $card->fresh()->current_balance);
    }

    #[Test]
    public function updating_opening_balance_via_the_rest_api_recomputes_current_balance(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'current_balance' => 500,
            'opening_balance' => 500,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/credit-cards/{$card->id}", [
            'opening_balance' => 700,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.opening_balance', 700)
            ->assertJsonPath('data.current_balance', 700);

        $this->assertSame(700.0, (float) $card->fresh()->current_balance);
    }

    #[Test]
    public function store_rejects_explicit_null_opening_balance_with_a_validation_error_not_a_database_error(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/credit-cards', [
            'name' => 'Null opening balance card',
            'account_id' => $account->id,
            'type' => 'charge',
            'brand' => 'visa',
            'credit_limit' => 5000,
            'statement_day' => 6,
            'due_day' => 19,
            'status' => 'active',
            'opening_balance' => null,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_balance']);

        $this->assertDatabaseMissing('credit_cards', [
            'name' => 'Null opening balance card',
        ]);
    }

    #[Test]
    public function update_rejects_explicit_null_opening_balance_with_a_validation_error_not_a_database_error(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/credit-cards/{$card->id}", [
            'opening_balance' => null,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['opening_balance']);
    }
}
