<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardExpense;
use App\Models\Notification;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionRenewalHistory;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubscriptionRenewalHistoryTest extends TestCase
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
            'user_id' => $this->user->id,
            'balance' => 100,
            'opening_balance' => 100,
            'currency' => 'EUR',
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
            'annual_cost' => 10,
            'monthly_cost' => 10,
            'day_of_month' => 23,
            'next_renewal_date' => '2026-04-23',
            'auto_create_transaction' => true,
        ], $overrides));

        // Observers recompute the costs on save: pin the fixture values.
        DB::table('subscriptions')->where('id', $subscription->id)->update(['monthly_cost' => 10, 'annual_cost' => 10]);

        return $subscription->fresh();
    }

    private function setPrice(Subscription $subscription, float $price): void
    {
        DB::table('subscriptions')->where('id', $subscription->id)->update(['monthly_cost' => $price, 'annual_cost' => $price]);
    }

    private function renew(Subscription $subscription, string $date): void
    {
        // Mirror the nightly job: it only processes the renewal the subscription is currently due for.
        DB::table('subscriptions')->where('id', $subscription->id)->update(['next_renewal_date' => $date]);

        app(SubscriptionService::class)->processRenewal($subscription->fresh(), Carbon::parse($date));
    }

    private function makeCard(): CreditCard
    {
        $settlement = Account::factory()->create(['user_id' => $this->user->id, 'currency' => 'EUR']);

        return CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $settlement->id]);
    }

    public function test_a_new_account_charge_stores_frequency_and_currency_snapshots(): void
    {
        $this->renew($this->subscription, '2026-04-23');

        $row = Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->firstOrFail();

        $this->assertSame('monthly', $row->renewal_frequency);
        $this->assertSame('EUR', $row->renewal_currency);
    }

    public function test_a_new_card_charge_stores_snapshots_from_the_settlement_account(): void
    {
        $card = $this->makeCard();
        $subscription = $this->makeSubscription(['account_id' => null, 'credit_card_id' => $card->id], 'annual');

        $this->renew($subscription, '2026-04-23');

        $row = CreditCardExpense::query()->where('subscription_id', $subscription->id)->firstOrFail();

        $this->assertSame('annual', $row->renewal_frequency);
        $this->assertSame('EUR', $row->renewal_currency);
    }

    public function test_a_retry_keeps_the_original_amount_and_moves_the_balance_once(): void
    {
        $this->renew($this->subscription, '2026-04-23');
        $this->setPrice($this->subscription, 15.99);
        $this->renew($this->subscription, '2026-04-23');

        $rows = Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->get();

        $this->assertCount(1, $rows);
        $this->assertEquals(-10, $rows->first()->amount);
        $this->assertEquals(90, $this->account->fresh()->balance);
    }

    public function test_a_card_retry_keeps_the_original_amount(): void
    {
        $card = $this->makeCard();
        $subscription = $this->makeSubscription(['account_id' => null, 'credit_card_id' => $card->id]);

        $this->renew($subscription, '2026-04-23');
        $this->setPrice($subscription, 15.99);
        $this->renew($subscription, '2026-04-23');

        $rows = CreditCardExpense::query()->where('subscription_id', $subscription->id)->get();

        $this->assertCount(1, $rows);
        $this->assertEquals(10, $rows->first()->amount);
    }

    public function test_a_soft_deleted_same_date_posting_is_restored_not_duplicated(): void
    {
        $this->renew($this->subscription, '2026-04-23');
        Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->firstOrFail()->delete();

        $this->renew($this->subscription, '2026-04-23');

        $this->assertSame(1, Transaction::withoutGlobalScopes()->withTrashed()->where('subscription_id', $this->subscription->id)->count());
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->count());
    }

    public function test_overdue_renewals_are_caught_up_after_a_missed_scheduler_day(): void
    {
        DB::table('subscriptions')->where('id', $this->subscription->id)->update(['next_renewal_date' => '2026-02-23']);

        $synced = app(SubscriptionService::class)->syncDueRenewals(Carbon::parse('2026-04-23')->endOfDay());

        $this->assertSame(3, $synced);
        $this->assertSame(3, Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->count());
        $this->assertSame('2026-05-23', $this->subscription->fresh()->next_renewal_date->toDateString());
    }

    public function test_history_returns_the_two_latest_distinct_dates_across_account_and_card(): void
    {
        $this->renew($this->subscription, '2026-02-23');
        $this->setPrice($this->subscription, 12);
        $this->renew($this->subscription, '2026-03-23');

        $card = $this->makeCard();
        DB::table('subscriptions')->where('id', $this->subscription->id)->update(['account_id' => null, 'credit_card_id' => $card->id]);
        $this->setPrice($this->subscription, 15.99);
        $this->renew($this->subscription, '2026-04-23');

        $history = app(SubscriptionRenewalHistory::class)->latest($this->subscription->fresh());

        $this->assertCount(2, $history);
        $this->assertSame(['2026-04-23', 'card', '15.99', 1599], [$history[0]['date'], $history[0]['source'], $history[0]['amount'], $history[0]['amount_minor']]);
        $this->assertSame(['2026-03-23', 'account', '12.00', 1200], [$history[1]['date'], $history[1]['source'], $history[1]['amount'], $history[1]['amount_minor']]);
        $this->assertFalse($history[0]['ambiguous']);
    }

    public function test_legacy_rows_without_snapshots_come_back_as_unknown(): void
    {
        $this->renew($this->subscription, '2026-04-23');
        DB::table('transactions')->where('subscription_id', $this->subscription->id)->update(['renewal_frequency' => null, 'renewal_currency' => null]);

        $history = app(SubscriptionRenewalHistory::class)->latest($this->subscription->fresh());

        $this->assertNull($history[0]['frequency']);
        $this->assertNull($history[0]['currency']);
    }

    public function test_the_same_date_in_both_tables_is_flagged_ambiguous(): void
    {
        $this->renew($this->subscription, '2026-04-23');
        $card = $this->makeCard();
        CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'subscription_id' => $this->subscription->id,
            'spent_at' => '2026-04-23',
            'posted_at' => '2026-04-23',
            'subscription_renewal_date' => '2026-04-23',
            'amount' => 10,
            'description' => 'Renewal',
        ]);

        $history = app(SubscriptionRenewalHistory::class)->latest($this->subscription->fresh());

        $this->assertCount(1, $history);
        $this->assertTrue($history[0]['ambiguous']);
    }

    public function test_history_never_includes_another_owners_postings(): void
    {
        $this->renew($this->subscription, '2026-04-23');

        $other = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_id' => $other->id, 'currency' => 'EUR']);
        Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->update(['user_id' => $other->id, 'account_id' => $otherAccount->id]);

        $history = app(SubscriptionRenewalHistory::class)->latest($this->subscription->fresh());

        $this->assertSame([], $history);
    }

    public function test_a_soft_deleted_charge_is_not_part_of_the_history(): void
    {
        $this->renew($this->subscription, '2026-03-23');
        $this->setPrice($this->subscription, 15.99);
        $this->renew($this->subscription, '2026-04-23');
        Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)
            ->whereDate('subscription_renewal_date', '2026-04-23')->firstOrFail()->delete();

        $history = app(SubscriptionRenewalHistory::class)->latest($this->subscription->fresh());

        $this->assertCount(1, $history);
        $this->assertSame('2026-03-23', $history[0]['date']);
    }

    public function test_a_renewal_already_posted_on_the_card_is_not_posted_again_on_the_account(): void
    {
        $card = $this->makeCard();
        DB::table('subscriptions')->where('id', $this->subscription->id)->update(['account_id' => null, 'credit_card_id' => $card->id]);
        $this->renew($this->subscription, '2026-04-23');

        // A run holding a stale instance sees the old account source after the switch back.
        $stale = $this->subscription->fresh();
        DB::table('subscriptions')->where('id', $this->subscription->id)->update(['account_id' => $this->account->id, 'credit_card_id' => null]);
        app(SubscriptionService::class)->processRenewal($stale, Carbon::parse('2026-04-23'));

        $this->assertSame(1, CreditCardExpense::query()->where('subscription_id', $this->subscription->id)->count());
        $this->assertSame(0, Transaction::withoutGlobalScopes()->withTrashed()->where('subscription_id', $this->subscription->id)->count());
        $this->assertEquals(100, $this->account->fresh()->balance);
    }

    public function test_a_renewal_already_posted_on_the_account_is_not_posted_again_on_the_card(): void
    {
        $this->renew($this->subscription, '2026-04-23');

        $card = $this->makeCard();
        DB::table('subscriptions')->where('id', $this->subscription->id)->update(['account_id' => null, 'credit_card_id' => $card->id]);
        app(SubscriptionService::class)->processRenewal($this->subscription->fresh(), Carbon::parse('2026-04-23'));

        $this->assertSame(0, CreditCardExpense::query()->where('subscription_id', $this->subscription->id)->count());
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->count());
    }

    public function test_repeating_an_old_renewal_does_not_rewind_the_next_renewal_date(): void
    {
        $this->renew($this->subscription, '2026-04-23');
        $this->renew($this->subscription, '2026-05-23');
        $this->assertSame('2026-06-23', $this->subscription->fresh()->next_renewal_date->toDateString());

        app(SubscriptionService::class)->processRenewal($this->subscription->fresh(), Carbon::parse('2026-04-23'));

        $this->assertSame('2026-06-23', $this->subscription->fresh()->next_renewal_date->toDateString());
        $this->assertSame(2, Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->count());
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function changesAfterSelection(): array
    {
        return [
            'cancelled' => [['status' => 'cancelled']],
            'inactive' => [['status' => 'inactive']],
            'automatic posting off' => [['auto_create_transaction' => false]],
            'postponed' => [['next_renewal_date' => '2026-05-23']],
        ];
    }

    /** @param array<string, mixed> $change */
    #[\PHPUnit\Framework\Attributes\DataProvider('changesAfterSelection')]
    public function test_a_renewal_selected_before_the_lock_is_dropped_if_the_subscription_changed_meanwhile(array $change): void
    {
        // The nightly job selected this instance while the renewal was due...
        $selected = $this->subscription->fresh();
        // ...and the owner changed it before processRenewal() got the lock.
        DB::table('subscriptions')->where('id', $this->subscription->id)->update($change);

        $processed = app(SubscriptionService::class)->processRenewal($selected, Carbon::parse('2026-04-23'));

        $this->assertFalse($processed);
        $this->assertSame(0, Transaction::withoutGlobalScopes()->withTrashed()->where('subscription_id', $this->subscription->id)->count());
        $this->assertEquals(100, $this->account->fresh()->balance);
        $this->assertSame(0, Notification::query()->withoutUserScope()->where('related_id', $this->subscription->id)->count());
    }
}
