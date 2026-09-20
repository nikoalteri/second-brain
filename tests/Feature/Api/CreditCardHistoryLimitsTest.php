<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreditCardHistoryLimitsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    private function cardWithHistory(int $cycles, int $expenses): CreditCard
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $account->id]);

        for ($month = 1; $month <= $cycles; $month++) {
            CreditCardCycle::factory()->create([
                'credit_card_id' => $card->id,
                'period_month' => sprintf('2025-%02d', $month),
                'period_start_date' => sprintf('2025-%02d-01', $month),
                'statement_date' => sprintf('2025-%02d-28', $month),
                'due_date' => sprintf('2025-%02d-28', $month),
            ]);
        }

        for ($day = 1; $day <= $expenses; $day++) {
            CreditCardExpense::create([
                'credit_card_id' => $card->id,
                'spent_at' => now()->subDays($day),
                'amount' => 10,
                'description' => "Expense {$day}",
            ]);
        }

        return $card;
    }

    public function test_the_card_history_is_limited_and_reports_what_it_left_out(): void
    {
        $card = $this->cardWithHistory(cycles: 5, expenses: 7);
        // Creating expenses also creates the current cycle, so count what actually exists.
        $totalCycles = CreditCardCycle::query()->where('credit_card_id', $card->id)->count();

        $response = $this->getJson("/api/v1/credit-cards/{$card->id}?cycles_limit=2&expenses_limit=3")->assertOk();

        $response->assertJsonCount(2, 'data.cycles')
            ->assertJsonCount(3, 'data.expenses')
            ->assertJsonPath('history.cycles.total', $totalCycles)
            ->assertJsonPath('history.cycles.returned', 2)
            ->assertJsonPath('history.expenses.total', 7)
            ->assertJsonPath('history.expenses.returned', 3);

        // The most recent rows are the ones kept.
        $this->assertSame('Expense 1', $response->json('data.expenses.0.description'));
    }

    public function test_a_small_card_history_is_returned_whole_by_default(): void
    {
        $card = $this->cardWithHistory(cycles: 3, expenses: 4);
        $totalCycles = CreditCardCycle::query()->where('credit_card_id', $card->id)->count();

        $this->getJson("/api/v1/credit-cards/{$card->id}")
            ->assertOk()
            ->assertJsonCount($totalCycles, 'data.cycles')
            ->assertJsonCount(4, 'data.expenses')
            ->assertJsonPath('history.expenses.total', 4)
            ->assertJsonPath('history.expenses.returned', 4);
    }

    public function test_the_history_limits_are_capped(): void
    {
        $card = $this->cardWithHistory(cycles: 1, expenses: 1);

        $this->getJson("/api/v1/credit-cards/{$card->id}?cycles_limit=100000&expenses_limit=100000&payments_limit=100000")
            ->assertOk()
            ->assertJsonPath('history.cycles.limit', 120)
            ->assertJsonPath('history.expenses.limit', 1000)
            ->assertJsonPath('history.payments.limit', 500);
    }
}
