<?php

namespace Tests\Unit;

use App\Enums\SubscriptionStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;
    private User $user;
    private SubscriptionFrequency $monthlyFrequency;
    private SubscriptionFrequency $annualFrequency;
    private SubscriptionFrequency $biennialFrequency;

    public function setUp(): void
    {
        parent::setUp();
        $this->service = app(SubscriptionService::class);
        $this->user = User::factory()->create();
        $this->monthlyFrequency = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();
        $this->annualFrequency = SubscriptionFrequency::query()->where('slug', 'annual')->firstOrFail();
        $this->biennialFrequency = SubscriptionFrequency::query()->where('slug', 'biennial')->firstOrFail();
    }

    /** @test */
    public function calculate_monthly_cost_uses_frequency_interval(): void
    {
        $this->assertEquals(12.00, $this->service->calculateMonthlyCost(12.00, $this->monthlyFrequency));
        $this->assertEquals(10.00, $this->service->calculateMonthlyCost(120.00, $this->annualFrequency));
        $this->assertEquals(10.00, $this->service->calculateMonthlyCost(240.00, $this->biennialFrequency));
    }

    /** @test */
    public function calculate_annual_cost_uses_frequency_interval(): void
    {
        $this->assertEquals(12.00, $this->service->calculateAnnualCost(12.00, $this->monthlyFrequency));
        $this->assertEquals(120.00, $this->service->calculateAnnualCost(10.00, $this->annualFrequency));
        $this->assertEquals(240.00, $this->service->calculateAnnualCost(10.00, $this->biennialFrequency));
    }

    /** @test */
    public function get_monthly_total_for_user_subscriptions(): void
    {
        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'subscription_frequency_id' => $this->monthlyFrequency->id,
            'monthly_cost' => 10.00,
            'annual_cost' => 10.00,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'subscription_frequency_id' => $this->annualFrequency->id,
            'monthly_cost' => 10.00,
            'annual_cost' => 120.00,
            'status' => SubscriptionStatus::ACTIVE,
        ]);

        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'subscription_frequency_id' => $this->biennialFrequency->id,
            'monthly_cost' => 10.00,
            'annual_cost' => 240.00,
            'status' => SubscriptionStatus::INACTIVE,
        ]);

        $this->assertEquals(20.00, $this->service->getMonthlyTotal($this->user->id));
    }

    /** @test */
    public function get_upcoming_renewals(): void
    {
        $now = Carbon::parse('2026-03-23');
        Carbon::setTestNow($now);

        Subscription::factory()->state([
            'user_id' => $this->user->id,
            'subscription_frequency_id' => $this->monthlyFrequency->id,
            'next_renewal_date' => $now->copy()->addDays(3),
            'status' => SubscriptionStatus::ACTIVE,
        ])->make()->saveQuietly();

        Subscription::factory()->state([
            'user_id' => $this->user->id,
            'subscription_frequency_id' => $this->annualFrequency->id,
            'next_renewal_date' => $now->copy()->addDays(10),
            'status' => SubscriptionStatus::ACTIVE,
        ])->make()->saveQuietly();

        Subscription::factory()->state([
            'user_id' => $this->user->id,
            'subscription_frequency_id' => $this->annualFrequency->id,
            'next_renewal_date' => $now->copy()->subDays(5),
            'status' => SubscriptionStatus::ACTIVE,
        ])->make()->saveQuietly();

        $upcoming = $this->service->getUpcomingRenewals(7, $this->user->id);

        $this->assertEquals(1, $upcoming->count());
        $this->assertEquals($now->copy()->addDays(3)->toDateString(), $upcoming->first()->next_renewal_date->toDateString());

        Carbon::setTestNow();
    }

    /** @test */
    public function calculate_next_renewal_date_respects_frequency_interval(): void
    {
        $monthlySubscription = Subscription::factory()->make([
            'subscription_frequency_id' => $this->monthlyFrequency->id,
            'day_of_month' => 15,
        ]);
        $monthlySubscription->setRelation('frequencyOption', $this->monthlyFrequency);

        $annualSubscription = Subscription::factory()->make([
            'subscription_frequency_id' => $this->annualFrequency->id,
            'day_of_month' => 15,
        ]);
        $annualSubscription->setRelation('frequencyOption', $this->annualFrequency);

        $biennialSubscription = Subscription::factory()->make([
            'subscription_frequency_id' => $this->biennialFrequency->id,
            'day_of_month' => 15,
        ]);
        $biennialSubscription->setRelation('frequencyOption', $this->biennialFrequency);

        $from = Carbon::parse('2026-03-10');

        $this->assertEquals('2026-04-15', $this->service->calculateNextRenewalDate($monthlySubscription, $from)->toDateString());
        $this->assertEquals('2027-03-15', $this->service->calculateNextRenewalDate($annualSubscription, $from)->toDateString());
        $this->assertEquals('2028-03-15', $this->service->calculateNextRenewalDate($biennialSubscription, $from)->toDateString());
    }

    /** @test */
    public function observer_calculates_monthly_equivalent_on_creation(): void
    {
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'subscription_frequency_id' => $this->annualFrequency->id,
            'monthly_cost' => null,
            'annual_cost' => 120.00,
        ]);

        $this->assertEquals(10.00, (float) $subscription->monthly_cost);
    }

    /** @test */
    public function due_account_backed_subscription_posts_transaction_and_advances_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-23'));

        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'subscription_frequency_id' => $this->monthlyFrequency->id,
            'annual_cost' => 19.99,
            'monthly_cost' => 19.99,
            'day_of_month' => 23,
            'next_renewal_date' => '2026-04-23',
            'auto_create_transaction' => true,
        ]);

        $synced = $this->service->syncDueRenewals(now()->endOfDay());

        $subscription->refresh();

        $this->assertSame(1, $synced);
        $this->assertDatabaseHas('transactions', [
            'subscription_id' => $subscription->id,
            'subscription_renewal_date' => '2026-04-23 00:00:00',
        ]);
        $this->assertSame('2026-05-23', $subscription->next_renewal_date?->toDateString());

        Carbon::setTestNow();
    }

    /** @test */
    public function due_credit_card_backed_subscription_posts_card_expense_and_advances_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-23'));

        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $creditCard = CreditCard::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => null,
            'credit_card_id' => $creditCard->id,
            'subscription_frequency_id' => $this->monthlyFrequency->id,
            'annual_cost' => 14.99,
            'monthly_cost' => 14.99,
            'day_of_month' => 23,
            'next_renewal_date' => '2026-04-23',
            'auto_create_transaction' => true,
        ]);

        $synced = $this->service->syncDueRenewals(now()->endOfDay());

        $subscription->refresh();

        $this->assertSame(1, $synced);
        $this->assertDatabaseHas('credit_card_expenses', [
            'subscription_id' => $subscription->id,
            'subscription_renewal_date' => '2026-04-23 00:00:00',
        ]);
        $this->assertSame('2026-05-23', $subscription->next_renewal_date?->toDateString());

        Carbon::setTestNow();
    }

    /** @test */
    public function has_posting_for_renewal_detects_existing_transaction(): void
    {
        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $account->id,
            'subscription_frequency_id' => $this->monthlyFrequency->id,
        ]);

        Transaction::create([
            'subscription_id' => $subscription->id,
            'subscription_renewal_date' => '2026-04-23',
            'account_id' => $account->id,
            'transaction_type_id' => \App\Models\TransactionType::query()->firstOrCreate(
                ['name' => 'Expense'],
                ['is_income' => false]
            )->id,
            'transaction_category_id' => null,
            'amount' => -10,
            'date' => '2026-04-23',
            'description' => 'Subscription renewal - Test',
            'notes' => null,
            'is_transfer' => false,
        ]);

        $this->assertTrue($this->service->hasPostingForRenewal($subscription, Carbon::parse('2026-04-23')));
    }
}
