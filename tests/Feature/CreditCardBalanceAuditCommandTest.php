<?php

namespace Tests\Feature;

use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CreditCardBalanceAuditCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_lists_every_active_card_oldest_first_with_no_date_based_filtering(): void
    {
        $account = Account::factory()->create();

        // Deliberately created "later" but seeded second, to prove ordering comes from
        // created_at, not insertion order.
        $newerCard = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Newer audit card',
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 1000,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);
        $newerCard->forceFill(['created_at' => Carbon::parse('2026-09-20')])->save();

        $olderCard = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Older audit card',
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 1000,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 400,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);
        $olderCard->forceFill(['created_at' => Carbon::parse('2026-07-01')])->save();

        $inactiveCard = CreditCard::create([
            'user_id' => $account->user_id,
            'account_id' => $account->id,
            'name' => 'Closed audit card',
            'type' => CreditCardType::REVOLVING,
            'credit_limit' => 1000,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::CLOSED,
            'stamp_duty_amount' => 2,
        ]);

        $exitCode = Artisan::call('credit-cards:balance-audit');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);

        $this->assertStringContainsString('Older audit card', $output);
        $this->assertStringContainsString('Newer audit card', $output);
        $this->assertStringNotContainsString('Closed audit card', $output);
        $this->assertStringContainsString('2 active card(s) listed, oldest first.', $output);
        $this->assertStringNotContainsString('CHECK', $output);

        // Oldest first: the older card's row must appear before the newer card's row.
        $this->assertLessThan(
            strpos($output, 'Newer audit card'),
            strpos($output, 'Older audit card')
        );

        $olderCard->refresh();
        $this->assertSame(400.0, (float) $olderCard->current_balance);
    }
}
