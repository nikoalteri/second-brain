<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardExpense;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class CreditCardWriteAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private function makeCard(User $user): CreditCard
    {
        $account = Account::factory()->create(['user_id' => $user->id]);

        return CreditCard::factory()->charge()->create(['user_id' => $user->id, 'account_id' => $account->id]);
    }

    public function test_expense_store_is_rolled_back_when_a_later_step_fails(): void
    {
        $user = User::factory()->create();
        $card = $this->makeCard($user);

        // Registered after the app observer, so it runs once the balance sync has completed.
        CreditCardExpense::created(function () {
            throw new RuntimeException('failure after observer');
        });

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/credit-cards/{$card->id}/expenses", [
            'spent_at' => '2026-04-20', 'posted_at' => '2026-04-21', 'amount' => 10, 'description' => 'X',
        ])->assertStatus(500);

        $this->assertSame(0, CreditCardExpense::withoutGlobalScopes()->count());
    }

    public function test_expense_update_is_rolled_back_when_a_later_step_fails(): void
    {
        $user = User::factory()->create();
        $card = $this->makeCard($user);

        Sanctum::actingAs($user);

        $expenseId = $this->postJson("/api/v1/credit-cards/{$card->id}/expenses", [
            'spent_at' => '2026-04-20', 'posted_at' => '2026-04-21', 'amount' => 10, 'description' => 'Original',
        ])->assertCreated()->json('data.id');

        CreditCardExpense::updated(function () {
            throw new RuntimeException('failure after observer');
        });

        $this->putJson("/api/v1/credit-cards/{$card->id}/expenses/{$expenseId}", ['description' => 'Changed'])
            ->assertStatus(500);

        $this->assertSame('Original', CreditCardExpense::withoutGlobalScopes()->find($expenseId)->description);
    }

    public function test_credit_card_update_is_rolled_back_when_a_later_step_fails(): void
    {
        $user = User::factory()->create();
        $card = $this->makeCard($user);
        $originalName = $card->name;

        CreditCard::updated(function () {
            throw new RuntimeException('failure after observer');
        });

        Sanctum::actingAs($user);

        $this->putJson("/api/v1/credit-cards/{$card->id}", ['name' => 'Changed'])->assertStatus(500);

        $this->assertSame($originalName, $card->fresh()->name);
    }
}
