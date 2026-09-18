<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditCardGraphQLSchemaTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id]);

        Sanctum::actingAs($this->user);
    }

    public function test_a_charge_card_without_fixed_payment_and_rate_can_be_queried(): void
    {
        $card = CreditCard::factory()->charge()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'fixed_payment' => null,
            'interest_rate' => null,
        ]);

        $response = $this->postJson('/graphql', [
            'query' => 'query ($id: ID!) { creditCard(id: $id) { id fixed_payment interest_rate } }',
            'variables' => ['id' => $card->id],
        ]);

        $this->assertEmpty($response->json('errors'));
        $this->assertSame((string) $card->id, $response->json('data.creditCard.id'));
        $this->assertNull($response->json('data.creditCard.fixed_payment'));
        $this->assertNull($response->json('data.creditCard.interest_rate'));
    }

    public function test_opening_balance_and_stamp_duty_flag_are_exposed(): void
    {
        $card = CreditCard::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'opening_balance' => 250,
            'fixed_payment_includes_stamp_duty' => true,
        ]);

        $response = $this->postJson('/graphql', [
            'query' => 'query ($id: ID!) { creditCard(id: $id) { opening_balance fixed_payment_includes_stamp_duty } }',
            'variables' => ['id' => $card->id],
        ]);

        $this->assertEmpty($response->json('errors'));
        $this->assertEquals(250, $response->json('data.creditCard.opening_balance'));
        $this->assertTrue($response->json('data.creditCard.fixed_payment_includes_stamp_duty'));
    }

    public function test_opening_balance_and_stamp_duty_flag_can_be_written(): void
    {
        $card = CreditCard::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'opening_balance' => 100,
            'fixed_payment_includes_stamp_duty' => false,
        ]);

        $response = $this->postJson('/graphql', [
            'query' => 'mutation ($id: ID!) { updateCreditCard(id: $id, input: {opening_balance: 400, fixed_payment_includes_stamp_duty: true}) { id } }',
            'variables' => ['id' => $card->id],
        ]);

        $this->assertEmpty($response->json('errors'));
        $fresh = $card->fresh();
        $this->assertEquals(400, $fresh->opening_balance);
        $this->assertTrue((bool) $fresh->fixed_payment_includes_stamp_duty);
    }
}
