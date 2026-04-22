<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditCardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_credit_cards(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        $accountA = Account::factory()->create(['user_id' => $userA->id]);
        $accountB = Account::factory()->create(['user_id' => $userB->id]);

        CreditCard::factory()->count(2)->create(['user_id' => $userA->id, 'account_id' => $accountA->id]);
        CreditCard::factory()->count(3)->create(['user_id' => $userB->id, 'account_id' => $accountB->id]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/credit-cards');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_credit_card_show_includes_cycles(): void
    {
        $user       = User::factory()->create();
        $account    = Account::factory()->create(['user_id' => $user->id]);
        $creditCard = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $creditCard->id,
            'period_start_date' => '2026-04-06',
            'statement_date' => '2026-05-06',
            'due_date' => '2026-05-19',
        ]);
        CreditCardExpense::factory()->create([
            'credit_card_id' => $creditCard->id,
            'credit_card_cycle_id' => $cycle->id,
            'description' => 'Groceries',
            'spent_at' => '2026-04-20',
            'amount' => 123.45,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/credit-cards/{$creditCard->id}");

        $response->assertOk()
            ->assertJsonPath('data.cycles.0.period_start_date', '2026-04-06')
            ->assertJsonPath('data.cycles.0.statement_date', '2026-05-06')
            ->assertJsonPath('data.cycles.0.expenses.0.description', 'Groceries')
            ->assertJsonPath('data.expenses.0.description', 'Groceries');
    }

    public function test_user_cannot_delete_another_users_credit_card(): void
    {
        $userA      = User::factory()->create();
        $userB      = User::factory()->create();
        $account    = Account::factory()->create(['user_id' => $userA->id]);
        $creditCard = CreditCard::factory()->create(['user_id' => $userA->id, 'account_id' => $account->id]);

        Sanctum::actingAs($userB);

        $response = $this->deleteJson("/api/v1/credit-cards/{$creditCard->id}");

        // HasUserScoping filters out userA's card for userB → 404
        $response->assertStatus(404);
    }

    public function test_user_can_update_credit_card_account_and_start_date(): void
    {
        $user = User::factory()->create();
        $originalAccount = Account::factory()->create(['user_id' => $user->id]);
        $newAccount = Account::factory()->create(['user_id' => $user->id]);
        $creditCard = CreditCard::factory()->create([
            'user_id' => $user->id,
            'account_id' => $originalAccount->id,
            'start_date' => '2026-01-01',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/credit-cards/{$creditCard->id}", [
            'account_id' => $newAccount->id,
            'start_date' => '2026-02-01',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.account_id', $newAccount->id)
            ->assertJsonPath('data.start_date', '2026-02-01');

        $creditCard->refresh();

        $this->assertSame($newAccount->id, $creditCard->account_id);
        $this->assertSame('2026-02-01', $creditCard->start_date?->toDateString());
    }

    public function test_charge_card_create_clears_interest_fields(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/credit-cards', [
            'name' => 'Charge Card',
            'account_id' => $account->id,
            'type' => 'charge',
            'credit_limit' => 5000,
            'fixed_payment' => 250,
            'interest_rate' => 17.5,
            'statement_day' => 6,
            'due_day' => 19,
            'status' => 'active',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.type', 'charge')
            ->assertJsonPath('data.fixed_payment', null)
            ->assertJsonPath('data.interest_rate', null)
            ->assertJsonPath('data.interest_calculation_method', null);

        $card = CreditCard::findOrFail($response->json('data.id'));

        $this->assertNull($card->fixed_payment);
        $this->assertNull($card->interest_rate);
        $this->assertSame('daily_balance', $card->interest_calculation_method?->value);
    }

    public function test_switching_card_to_charge_clears_interest_fields(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $creditCard = CreditCard::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'type' => 'revolving',
            'fixed_payment' => 250,
            'interest_rate' => 12,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/credit-cards/{$creditCard->id}", [
            'type' => 'charge',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'charge')
            ->assertJsonPath('data.fixed_payment', null)
            ->assertJsonPath('data.interest_rate', null)
            ->assertJsonPath('data.interest_calculation_method', null);

        $creditCard->refresh();

        $this->assertNull($creditCard->fixed_payment);
        $this->assertNull($creditCard->interest_rate);
        $this->assertSame('daily_balance', $creditCard->interest_calculation_method?->value);
    }

    public function test_user_can_create_credit_card_cycle(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $creditCard = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/credit-cards/{$creditCard->id}/cycles", [
            'period_start_date' => '2026-04-06',
            'statement_date' => '2026-05-06',
            'due_date' => '2026-05-19',
            'status' => 'open',
            'total_spent' => 0,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.period_month', '2026-04')
            ->assertJsonPath('data.statement_date', '2026-05-06');

        $createdCycle = $creditCard->fresh()->cycles()->latest('id')->first();

        $this->assertNotNull($createdCycle);
        $this->assertSame('2026-04', $createdCycle->period_month);
        $this->assertSame('2026-05-06', $createdCycle->statement_date?->toDateString());
    }

    public function test_user_can_issue_open_credit_card_cycle(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $creditCard = CreditCard::factory()->charge()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
        ]);
        $cycle = CreditCardCycle::factory()->create([
            'credit_card_id' => $creditCard->id,
            'period_start_date' => '2026-04-06',
            'statement_date' => '2026-05-06',
            'due_date' => '2026-05-19',
            'total_spent' => 200,
            'status' => 'open',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/credit-cards/{$creditCard->id}/cycles/{$cycle->id}/issue");

        $response->assertOk()
            ->assertJsonPath('data.status', 'issued')
            ->assertJsonPath('data.total_due', 202);

        $this->assertDatabaseHas('credit_card_cycles', [
            'id' => $cycle->id,
            'status' => 'issued',
        ]);
        $this->assertDatabaseHas('credit_card_payments', [
            'credit_card_cycle_id' => $cycle->id,
            'total_amount' => 202.00,
        ]);
    }

    public function test_user_can_create_update_and_delete_credit_card_expense(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $creditCard = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        Sanctum::actingAs($user);

        $createResponse = $this->postJson("/api/v1/credit-cards/{$creditCard->id}/expenses", [
            'spent_at' => '2026-04-08',
            'posted_at' => '2026-04-09',
            'amount' => 99.5,
            'description' => 'Online order',
            'notes' => 'First note',
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.description', 'Online order');

        $expenseId = $createResponse->json('data.id');

        $updateResponse = $this->putJson("/api/v1/credit-cards/{$creditCard->id}/expenses/{$expenseId}", [
            'description' => 'Updated order',
            'amount' => 120,
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('data.description', 'Updated order')
            ->assertJsonPath('data.amount', 120);

        $deleteResponse = $this->deleteJson("/api/v1/credit-cards/{$creditCard->id}/expenses/{$expenseId}");

        $deleteResponse->assertNoContent();
        $this->assertDatabaseMissing('credit_card_expenses', ['id' => $expenseId]);
    }

    public function test_user_cannot_add_expense_to_issued_credit_card_cycle(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $creditCard = CreditCard::factory()->charge()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        CreditCardCycle::factory()->issued()->create([
            'credit_card_id' => $creditCard->id,
            'period_month' => '2026-04',
            'period_start_date' => '2026-04-06',
            'statement_date' => '2026-05-06',
            'due_date' => '2026-05-19',
            'total_spent' => 200,
            'total_due' => 202,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/credit-cards/{$creditCard->id}/expenses", [
            'spent_at' => '2026-04-20',
            'posted_at' => '2026-04-21',
            'amount' => 99.5,
            'description' => 'Late expense',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['spent_at']);

        $this->assertDatabaseCount('credit_card_expenses', 0);
    }

    public function test_marking_credit_card_payment_paid_creates_posting_transaction(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $creditCard = CreditCard::factory()->charge()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        $cycle = CreditCardCycle::factory()->issued()->create([
            'credit_card_id' => $creditCard->id,
            'total_due' => 202,
        ]);
        $payment = CreditCardPayment::create([
            'credit_card_id' => $creditCard->id,
            'credit_card_cycle_id' => $cycle->id,
            'due_date' => '2026-05-19',
            'installment_amount' => 200,
            'interest_amount' => 0,
            'principal_amount' => 200,
            'stamp_duty_amount' => 2,
            'total_amount' => 202,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/credit-cards/{$creditCard->id}/payments/{$payment->id}/mark-paid");

        $response->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.transaction_posted', true);

        $payment->refresh();

        $this->assertSame('paid', $payment->status->value);
        $this->assertDatabaseHas('transactions', [
            'credit_card_payment_id' => $payment->id,
        ]);
    }
}
