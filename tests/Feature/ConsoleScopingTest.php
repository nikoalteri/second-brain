<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * D-04 (Phase 18): these tests lock in HasUserScoping's intentional no-op in
 * non-HTTP contexts. The scheduled commands in routes/console.php query
 * CreditCard/Loan/Subscription with no explicit user_id filter and MUST see
 * every user's records. If a future change makes the global scope fail-closed
 * under cron, these tests break by design.
 */
class ConsoleScopingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_generate_cycles_command_processes_all_users_active_cards(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        $cardA = CreditCard::create([
            'user_id' => $accountA->user_id,
            'account_id' => $accountA->id,
            'name' => 'Card A',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 0,
        ]);

        $cardB = CreditCard::create([
            'user_id' => $accountB->user_id,
            'account_id' => $accountB->id,
            'name' => 'Card B',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 0,
        ]);

        $this->artisan('credit-cards:generate-cycles --month=2026-03')
            ->expectsOutputToContain('2 cards')
            ->assertExitCode(0);

        $this->assertDatabaseHas('credit_card_cycles', [
            'credit_card_id' => $cardA->id,
            'period_month' => '2026-03',
        ]);
        $this->assertDatabaseHas('credit_card_cycles', [
            'credit_card_id' => $cardB->id,
            'period_month' => '2026-03',
        ]);
    }

    #[Test]
    public function test_generate_cycles_command_is_unaffected_by_ambient_authentication(): void
    {
        $accountA = Account::factory()->create();
        $accountB = Account::factory()->create();

        $cardA = CreditCard::create([
            'user_id' => $accountA->user_id,
            'account_id' => $accountA->id,
            'name' => 'Card A',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 0,
        ]);

        $cardB = CreditCard::create([
            'user_id' => $accountB->user_id,
            'account_id' => $accountB->id,
            'name' => 'Card B',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 0,
        ]);

        Sanctum::actingAs($accountA->user);

        $this->artisan('credit-cards:generate-cycles --month=2026-03')
            ->assertExitCode(0);

        // If this fails, the cron job would silently process only the
        // last-authenticated user's cards when run in a process with
        // ambient auth (e.g. queued from a web request).
        $this->assertDatabaseHas('credit_card_cycles', [
            'credit_card_id' => $cardA->id,
            'period_month' => '2026-03',
        ]);
        $this->assertDatabaseHas('credit_card_cycles', [
            'credit_card_id' => $cardB->id,
            'period_month' => '2026-03',
        ]);
    }
}
