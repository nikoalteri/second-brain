<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreditCardStampDutyFlagTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function factory_created_card_defaults_flag_to_false(): void
    {
        $creditCard = CreditCard::factory()->create();

        $this->assertFalse($creditCard->fixed_payment_includes_stamp_duty);
    }

    #[Test]
    public function creating_card_via_api_with_flag_true_persists_and_returns_true(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/credit-cards', [
            'name' => 'Fixture Revolving Card',
            'account_id' => $account->id,
            'type' => 'revolving',
            'brand' => 'visa',
            'credit_limit' => 4000,
            'fixed_payment' => 250,
            'interest_rate' => 14,
            'stamp_duty_amount' => 2,
            'fixed_payment_includes_stamp_duty' => true,
            'statement_day' => 6,
            'due_day' => 20,
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.fixed_payment_includes_stamp_duty', true);

        $creditCard = CreditCard::findOrFail($response->json('data.id'));
        $this->assertTrue($creditCard->fixed_payment_includes_stamp_duty);
    }

    #[Test]
    public function updating_card_via_api_with_flag_false_persists_false(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $creditCard = CreditCard::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'fixed_payment_includes_stamp_duty' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/credit-cards/{$creditCard->id}", [
            'fixed_payment_includes_stamp_duty' => false,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.fixed_payment_includes_stamp_duty', false);

        $creditCard->refresh();
        $this->assertFalse($creditCard->fixed_payment_includes_stamp_duty);
    }

    #[Test]
    public function creating_card_via_api_omitting_flag_defaults_to_false(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/credit-cards', [
            'name' => 'Fixture Revolving Card',
            'account_id' => $account->id,
            'type' => 'revolving',
            'brand' => 'visa',
            'credit_limit' => 4000,
            'fixed_payment' => 250,
            'interest_rate' => 14,
            'stamp_duty_amount' => 2,
            'statement_day' => 6,
            'due_day' => 20,
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.fixed_payment_includes_stamp_duty', false);

        $creditCard = CreditCard::findOrFail($response->json('data.id'));
        $this->assertFalse($creditCard->fixed_payment_includes_stamp_duty);
    }
}
