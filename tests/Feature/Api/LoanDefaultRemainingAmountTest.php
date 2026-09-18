<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanDefaultRemainingAmountTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Car loan',
            'account_id' => $this->account->id,
            'total_amount' => 1200,
            'monthly_payment' => 100,
            'withdrawal_day' => 5,
            'start_date' => '2026-01-05',
            'total_installments' => 12,
            'paid_installments' => 0,
            'status' => 'active',
        ], $overrides);
    }

    public function test_rest_store_defaults_remaining_amount_to_the_total(): void
    {
        $this->postJson('/api/v1/loans', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.remaining_amount', 1200);
    }

    public function test_rest_store_accepts_an_explicit_null_remaining_amount(): void
    {
        $this->postJson('/api/v1/loans', $this->payload(['remaining_amount' => null]))
            ->assertCreated()
            ->assertJsonPath('data.remaining_amount', 1200);
    }

    public function test_graphql_create_defaults_remaining_amount_to_the_total(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation ($a: ID!) { createLoan(input: {account_id: $a, name: "X", total_amount: 900, monthly_payment: 90, withdrawal_day: 5, start_date: "2026-01-05", total_installments: 10, paid_installments: 0, status: "active"}) { id remaining_amount } }',
            'variables' => ['a' => $this->account->id],
        ]);

        $this->assertEmpty($response->json('errors'));
        $this->assertEquals(900, $response->json('data.createLoan.remaining_amount'));
    }

    public function test_an_explicit_remaining_amount_is_kept_on_graphql_create(): void
    {
        $response = $this->postJson('/graphql', [
            'query' => 'mutation ($a: ID!) { createLoan(input: {account_id: $a, name: "X", total_amount: 900, monthly_payment: 90, withdrawal_day: 5, start_date: "2026-01-05", total_installments: 10, paid_installments: 3, remaining_amount: 630, status: "active"}) { id } }',
            'variables' => ['a' => $this->account->id],
        ]);

        $this->assertEmpty($response->json('errors'));
        $this->assertEquals(630, Loan::withoutGlobalScopes()->firstOrFail()->remaining_amount);
    }
}
