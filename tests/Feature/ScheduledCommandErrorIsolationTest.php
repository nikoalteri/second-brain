<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CreditCardStatus;
use App\Enums\CreditCardType;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Services\CreditCardCycleService;
use App\Services\LoanScheduleService;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Each of these nightly commands loops over every active card/loan/subscription across every
 * user. Before this fix, one item throwing an exception aborted the whole run, silently leaving
 * every item after it unprocessed for that night. These tests force one item to throw (via a
 * partial mock of the real service, so the command's own try/catch is what's under test, not an
 * incidental validation rule) and assert the rest of the batch still completes.
 */
class ScheduledCommandErrorIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_cycles_continues_past_one_cards_failure(): void
    {
        $goodAccount = Account::factory()->create();
        $goodCard = CreditCard::create([
            'user_id' => $goodAccount->user_id,
            'account_id' => $goodAccount->id,
            'name' => 'Good Card',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $badAccount = Account::factory()->create();
        $badCard = CreditCard::create([
            'user_id' => $badAccount->user_id,
            'account_id' => $badAccount->id,
            'name' => 'Bad Card',
            'type' => CreditCardType::CHARGE,
            'statement_day' => 28,
            'due_day' => 15,
            'skip_weekends' => true,
            'current_balance' => 0,
            'status' => CreditCardStatus::ACTIVE,
            'stamp_duty_amount' => 2,
        ]);

        $this->partialMock(CreditCardCycleService::class, function ($mock) use ($badCard) {
            $mock->shouldReceive('ensureCurrentMonthCycle')
                ->withArgs(fn (CreditCard $card) => $card->id === $badCard->id)
                ->andThrow(new \RuntimeException('simulated failure for the bad card'));
        });

        $this->artisan('credit-cards:generate-cycles --month=2026-03')
            ->expectsOutputToContain('1 failed')
            ->assertExitCode(1);

        $this->assertDatabaseHas('credit_card_cycles', [
            'credit_card_id' => $goodCard->id,
            'period_month' => '2026-03',
        ]);
        $this->assertDatabaseMissing('credit_card_cycles', [
            'credit_card_id' => $badCard->id,
        ]);
    }

    public function test_sync_installments_continues_past_one_loans_failure(): void
    {
        $goodAccount = Account::factory()->create();
        $goodLoan = Loan::factory()->create([
            'user_id' => $goodAccount->user_id,
            'account_id' => $goodAccount->id,
            'status' => 'active',
            'start_date' => now()->subMonths(2)->toDateString(),
            'withdrawal_day' => now()->day,
            'total_installments' => 12,
            'paid_installments' => 0,
        ]);

        $badAccount = Account::factory()->create();
        $badLoan = Loan::factory()->create([
            'user_id' => $badAccount->user_id,
            'account_id' => $badAccount->id,
            'status' => 'active',
            'start_date' => now()->subMonths(2)->toDateString(),
            'withdrawal_day' => now()->day,
            'total_installments' => 12,
            'paid_installments' => 0,
        ]);

        $this->partialMock(LoanScheduleService::class, function ($mock) use ($badLoan) {
            $mock->shouldReceive('generate')
                ->withArgs(fn (Loan $loan) => $loan->id === $badLoan->id)
                ->andThrow(new \RuntimeException('simulated failure for the bad loan'));
        });

        $this->artisan('loans:sync-installments')
            ->expectsOutputToContain('1 failed')
            ->assertExitCode(1);

        $this->assertDatabaseHas('loan_payments', ['loan_id' => $goodLoan->id]);
        $this->assertDatabaseMissing('loan_payments', ['loan_id' => $badLoan->id]);
    }

    public function test_sync_renewals_continues_past_one_subscriptions_failure(): void
    {
        $frequency = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();

        $goodAccount = Account::factory()->create();
        $goodSubscription = Subscription::factory()->create([
            'user_id' => $goodAccount->user_id,
            'account_id' => $goodAccount->id,
            'subscription_frequency_id' => $frequency->id,
            'next_renewal_date' => now()->subDay()->toDateString(),
            'auto_create_transaction' => true,
            'status' => 'active',
        ]);

        $badAccount = Account::factory()->create();
        $badSubscription = Subscription::factory()->create([
            'user_id' => $badAccount->user_id,
            'account_id' => $badAccount->id,
            'subscription_frequency_id' => $frequency->id,
            'next_renewal_date' => now()->subDay()->toDateString(),
            'auto_create_transaction' => true,
            'status' => 'active',
        ]);

        $yesterday = now()->subDay()->toDateString();

        $this->partialMock(SubscriptionService::class, function ($mock) use ($badSubscription) {
            $mock->shouldReceive('processRenewal')
                ->withArgs(fn (Subscription $subscription) => $subscription->id === $badSubscription->id)
                ->andThrow(new \RuntimeException('simulated failure for the bad subscription'));
        });

        $this->artisan('subscriptions:sync-renewals')
            ->expectsOutputToContain('1 renewal(s) processed')
            ->assertExitCode(0);

        $goodSubscription->refresh();
        $badSubscription->refresh();

        $this->assertNotSame($yesterday, $goodSubscription->next_renewal_date?->toDateString());
        $this->assertSame($yesterday, $badSubscription->next_renewal_date?->toDateString());
    }
}
