<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Notifications\NotificationConditionPublisher;
use App\Services\SubscriptionPriceHikeDetector;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class SubscriptionPriceHikeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $account;

    private Subscription $subscription;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-04-23'));

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create([
            'user_id' => $this->user->id, 'balance' => 1000, 'opening_balance' => 1000, 'currency' => 'EUR',
        ]);
        $this->subscription = $this->makeSubscription(['account_id' => $this->account->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param array<string, mixed> $overrides */
    private function makeSubscription(array $overrides = [], string $frequency = 'monthly'): Subscription
    {
        $subscription = Subscription::factory()->create(array_merge([
            'user_id' => $this->user->id,
            'subscription_frequency_id' => SubscriptionFrequency::query()->where('slug', $frequency)->firstOrFail()->id,
            'day_of_month' => 23,
            'next_renewal_date' => '2026-01-23',
            'auto_create_transaction' => true,
        ], $overrides));

        DB::table('subscriptions')->where('id', $subscription->id)->update(['monthly_cost' => 13.99, 'annual_cost' => 13.99]);

        return $subscription->fresh();
    }

    private function setPrice(Subscription $subscription, float $price): void
    {
        DB::table('subscriptions')->where('id', $subscription->id)->update(['monthly_cost' => $price, 'annual_cost' => $price]);
    }

    private function renew(Subscription $subscription, string $date): void
    {
        app(SubscriptionService::class)->processRenewal($subscription->fresh(), Carbon::parse($date));
    }

    private function hikes(?Subscription $subscription = null)
    {
        return Notification::query()->withoutUserScope()
            ->where('related_model', 'subscription')
            ->where('related_id', ($subscription ?? $this->subscription)->id)
            ->whereHas('condition', fn ($q) => $q->where('trigger', SubscriptionPriceHikeDetector::TRIGGER))
            ->get();
    }

    private function renewWithPrices(array $prices, ?Subscription $subscription = null): void
    {
        $subscription ??= $this->subscription;
        $date = Carbon::parse($subscription->next_renewal_date);

        foreach ($prices as $price) {
            $this->setPrice($subscription, $price);
            $this->renew($subscription, $date->toDateString());
            $date = $date->copy()->addMonth();
        }
    }

    public function test_an_increase_publishes_one_warning_for_the_owner(): void
    {
        $this->renewWithPrices([13.99, 15.99]);

        $hikes = $this->hikes();

        $this->assertCount(1, $hikes);
        $this->assertSame($this->user->id, $hikes->first()->user_id);
        $this->assertSame('warning', $hikes->first()->severity);
        $this->assertStringContainsString('EUR 13.99 to EUR 15.99 (+EUR 2.00)', $hikes->first()->message);
        $this->assertSame('/subscriptions', $hikes->first()->action_url);
        $this->assertSame('2026-02-23', $hikes->first()->condition->period_key);
    }

    public function test_a_one_cent_increase_qualifies(): void
    {
        $this->renewWithPrices([13.99, 14.00]);

        $this->assertCount(1, $this->hikes());
    }

    public function test_equal_and_lower_amounts_do_not_notify(): void
    {
        $this->renewWithPrices([13.99, 13.99, 9.99]);

        $this->assertCount(0, $this->hikes());
    }

    public function test_the_first_renewal_has_nothing_to_compare(): void
    {
        $this->renewWithPrices([13.99]);

        $this->assertCount(0, $this->hikes());
    }

    public function test_a_legacy_predecessor_without_snapshots_is_not_compared(): void
    {
        $this->renewWithPrices([13.99]);
        DB::table('transactions')->where('subscription_id', $this->subscription->id)->update(['renewal_frequency' => null, 'renewal_currency' => null]);

        $this->renewWithPrices([20.00], $this->subscription->fresh());

        $this->assertCount(0, $this->hikes());
    }

    public function test_a_changed_frequency_or_currency_is_not_compared(): void
    {
        $this->renewWithPrices([13.99]);
        DB::table('transactions')->where('subscription_id', $this->subscription->id)->update(['renewal_frequency' => 'annual']);
        $this->renewWithPrices([20.00], $this->subscription->fresh());
        $this->assertCount(0, $this->hikes());

        DB::table('transactions')->where('subscription_id', $this->subscription->id)->update(['renewal_frequency' => 'monthly', 'renewal_currency' => 'USD']);
        $this->renewWithPrices([25.00], $this->subscription->fresh());
        $this->assertCount(0, $this->hikes());
    }

    public function test_an_incompatible_predecessor_does_not_fall_back_to_an_older_charge(): void
    {
        $this->renewWithPrices([10.00, 12.00, 14.00]);
        $this->assertCount(2, $this->hikes());

        // Make the immediate predecessor of the next charge incomparable.
        DB::table('transactions')->where('subscription_id', $this->subscription->id)
            ->whereDate('subscription_renewal_date', '2026-03-23')->update(['renewal_currency' => null]);

        $this->renewWithPrices([30.00], $this->subscription->fresh());

        $this->assertCount(2, $this->hikes());
    }

    public function test_an_annual_subscription_compares_annual_amounts(): void
    {
        $subscription = $this->makeSubscription(['account_id' => $this->account->id, 'next_renewal_date' => '2026-01-23'], 'annual');

        foreach ([['2026-01-23', 100.00], ['2027-01-23', 120.00]] as [$date, $price]) {
            $this->setPrice($subscription, $price);
            $this->renew($subscription, $date);
        }

        $hikes = $this->hikes($subscription);
        $this->assertCount(1, $hikes);
        $this->assertStringContainsString('EUR 100.00 to EUR 120.00', $hikes->first()->message);
    }

    public function test_switching_between_account_and_card_keeps_one_history(): void
    {
        $settlement = Account::factory()->create(['user_id' => $this->user->id, 'currency' => 'EUR']);
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $settlement->id]);

        $this->renewWithPrices([13.99]);
        DB::table('subscriptions')->where('id', $this->subscription->id)->update(['account_id' => null, 'credit_card_id' => $card->id]);
        $this->renewWithPrices([15.99], $this->subscription->fresh());
        $this->assertCount(1, $this->hikes());

        DB::table('subscriptions')->where('id', $this->subscription->id)->update(['account_id' => $this->account->id, 'credit_card_id' => null]);
        $this->renewWithPrices([17.99], $this->subscription->fresh());
        $this->assertCount(2, $this->hikes());
    }

    public function test_a_retry_with_a_changed_price_and_a_second_sync_do_not_duplicate_the_notification(): void
    {
        $this->renewWithPrices([13.99, 15.99]);
        $this->setPrice($this->subscription, 99.00);
        $this->renew($this->subscription, '2026-02-23');

        app(SubscriptionService::class)->syncDueRenewals(Carbon::parse('2026-02-23')->endOfDay());

        $this->assertCount(1, $this->hikes());
    }

    public function test_a_failing_publisher_rolls_back_the_charge_balance_and_date_and_a_retry_publishes_once(): void
    {
        $this->renewWithPrices([13.99]);
        $this->setPrice($this->subscription, 15.99);
        $balance = $this->account->fresh()->balance;

        $this->app->bind(NotificationConditionPublisher::class, fn () => new class extends NotificationConditionPublisher
        {
            public function publish(int $userId, \App\Services\Notifications\ConditionSpec $spec): ?Notification
            {
                throw new RuntimeException('publisher down');
            }
        });

        try {
            $this->renew($this->subscription, '2026-02-23');
            $this->fail('The renewal should have failed.');
        } catch (RuntimeException) {
        }

        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->count());
        $this->assertEquals($balance, $this->account->fresh()->balance);
        $this->assertSame('2026-02-23', $this->subscription->fresh()->next_renewal_date->toDateString());

        $this->app->bind(NotificationConditionPublisher::class, fn () => new NotificationConditionPublisher);

        $this->renew($this->subscription, '2026-02-23');
        $this->renew($this->subscription, '2026-02-23');

        $this->assertCount(1, $this->hikes());
        $this->assertSame(2, Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->count());
    }

    public function test_a_configured_price_edit_alone_does_not_notify(): void
    {
        $this->renewWithPrices([13.99]);
        $this->setPrice($this->subscription, 50.00);

        $this->assertCount(0, $this->hikes());
    }
}
