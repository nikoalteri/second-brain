<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CreditCardPaymentStatus;
use App\Enums\LoanPaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Account;
use App\Models\CategoryBudget;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardPayment;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Notification;
use App\Models\NotificationCondition;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\BudgetService;
use App\Services\Notifications\FinanceNotificationDigest;
use App\Services\Notifications\FinanceNotificationEvaluator;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceNotificationDigestTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $other;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-17 02:05:00'));
        $this->user = User::factory()->create();
        $this->other = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function digest(string $date = '2026-10-17'): int
    {
        return $this->artisan('finance:notifications-digest', ['--date' => $date])->run();
    }

    private function notificationsFor(User $user, ?string $trigger = null)
    {
        $query = Notification::query()->withoutUserScope()->where('user_id', $user->id);

        if ($trigger !== null) {
            $query->whereIn('notification_condition_id', NotificationCondition::query()->where('trigger', $trigger)->pluck('id'));
        }

        return $query->get();
    }

    private function budgetSpend(User $user, float $budget, float $spent, string $name = 'Food'): TransactionCategory
    {
        $account = Account::factory()->create(['user_id' => $user->id]);
        $type = TransactionType::query()->firstOrCreate(['name' => 'Expenses'], ['is_income' => false]);
        $category = TransactionCategory::withoutGlobalScopes()->create(['user_id' => $user->id, 'name' => $name, 'is_active' => true]);

        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $category->id,
            'period_start' => '2026-10-01',
            'amount' => $budget,
        ]);
        Transaction::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id, 'transaction_type_id' => $type->id,
            'transaction_category_id' => $category->id, 'amount' => -$spent, 'date' => '2026-10-05',
        ]);

        return $category;
    }

    private function card(User $user, float $limit, float $balance): CreditCard
    {
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'credit_limit' => $limit]);
        // Observers recompute the balance from expenses; set the fixture value directly.
        DB::table('credit_cards')->where('id', $card->id)->update(['current_balance' => $balance]);

        return $card->fresh();
    }

    private function cardPayment(CreditCard $card, string $due, CreditCardPaymentStatus $status = CreditCardPaymentStatus::PENDING): CreditCardPayment
    {
        $cycle = CreditCardCycle::factory()->issued()->create(['credit_card_id' => $card->id, 'total_due' => 100]);

        $payment = CreditCardPayment::create([
            'credit_card_id' => $card->id, 'credit_card_cycle_id' => $cycle->id, 'due_date' => $due,
            'installment_amount' => 100, 'interest_amount' => 0, 'principal_amount' => 100,
            'stamp_duty_amount' => 0, 'total_amount' => 100, 'status' => $status,
        ]);
        DB::table('credit_cards')->where('id', $card->id)->update(['current_balance' => $card->current_balance]);

        return $payment;
    }

    private function loanPayment(User $user, string $due, LoanPaymentStatus $status = LoanPaymentStatus::PENDING, string $loanStatus = 'active'): LoanPayment
    {
        $loan = Loan::factory()->create(['user_id' => $user->id]);
        $payment = LoanPayment::create(['loan_id' => $loan->id, 'due_date' => $due, 'amount' => 200, 'status' => $status]);
        // Saving a payment resyncs the loan, so pin the fixture status afterwards.
        DB::table('loans')->where('id', $loan->id)->update(['status' => $loanStatus]);

        return $payment;
    }

    private function subscription(User $user, string $renewal, SubscriptionStatus $status = SubscriptionStatus::ACTIVE): Subscription
    {
        return Subscription::factory()->create([
            'user_id' => $user->id,
            'account_id' => Account::factory()->create(['user_id' => $user->id])->id,
            'subscription_frequency_id' => SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail()->id,
            'next_renewal_date' => $renewal,
            'auto_create_transaction' => false,
            'status' => $status,
        ]);
    }

    public function test_budget_thresholds_use_alert_status_and_only_the_owner_is_notified(): void
    {
        $this->budgetSpend($this->user, 100, 79, 'Below');
        $this->budgetSpend($this->user, 100, 80, 'Warn');
        $this->budgetSpend($this->user, 100, 100, 'Exceeded');
        $this->budgetSpend($this->user, 100, 120, 'Critical');

        $this->assertSame(0, $this->digest());

        $severities = $this->notificationsFor($this->user, 'budget')->pluck('severity')->sort()->values()->all();
        $this->assertSame(['critical', 'exceeded', 'warning'], $severities);
        $this->assertCount(0, $this->notificationsFor($this->other));
    }

    public function test_budget_escalation_resolution_and_reactivation(): void
    {
        $category = $this->budgetSpend($this->user, 100, 85);
        $account = Account::query()->where('user_id', $this->user->id)->first();
        $type = TransactionType::query()->where('name', 'Expenses')->first();
        $this->digest();
        $this->digest();
        $this->assertCount(1, $this->notificationsFor($this->user));

        $extra = Transaction::factory()->create([
            'user_id' => $this->user->id, 'account_id' => $account->id, 'transaction_type_id' => $type->id,
            'transaction_category_id' => $category->id, 'amount' => -40, 'date' => '2026-10-06',
        ]);
        $this->digest();
        $this->assertSame(['critical', 'warning'], $this->notificationsFor($this->user)->pluck('severity')->sort()->values()->all());

        $extra->delete();
        CategoryBudget::query()->withoutUserScope()->where('user_id', $this->user->id)->update(['amount' => 1000]);
        $this->digest();
        $this->assertFalse(NotificationCondition::query()->where('user_id', $this->user->id)->firstOrFail()->active);

        CategoryBudget::query()->withoutUserScope()->where('user_id', $this->user->id)->update(['amount' => 100]);
        $this->digest();
        $this->digest();
        $this->assertCount(3, $this->notificationsFor($this->user));
        $this->assertSame(2, NotificationCondition::query()->where('user_id', $this->user->id)->firstOrFail()->episode);
    }

    public function test_card_limit_boundary_and_unlimited_or_zero_limit_cards(): void
    {
        $this->card($this->user, 1000, 899.99);
        $at = $this->card($this->user, 1000, 900);
        $this->card($this->user, 0, 500);
        $unlimited = $this->card($this->user, 1000, 950);
        DB::table('credit_cards')->where('id', $unlimited->id)->update(['credit_limit' => null]);
        $this->card($this->other, 1000, 100);

        $this->digest();

        $notifications = $this->notificationsFor($this->user, 'credit_card_limit');
        $this->assertCount(1, $notifications);
        $this->assertSame($at->id, $notifications->first()->related_id);
        $this->assertSame('/credit-cards/'.$at->id, $notifications->first()->action_url);
        $this->assertCount(0, $this->notificationsFor($this->other));
    }

    public function test_card_payment_due_today_overdue_future_and_paid(): void
    {
        $card = $this->card($this->user, 5000, 0);
        $today = $this->cardPayment($card, '2026-10-17');
        $this->cardPayment($card, '2026-10-18');
        $this->cardPayment($card, '2026-10-01', CreditCardPaymentStatus::PAID);
        $overdue = $this->cardPayment($card, '2026-10-10');

        $this->digest();
        $this->digest();

        $byId = $this->notificationsFor($this->user, 'credit_card_payment')->keyBy('related_id');
        $this->assertCount(2, $byId);
        $this->assertSame('warning', $byId[$today->id]->severity);
        $this->assertSame('critical', $byId[$overdue->id]->severity);

        $overdue->forceFill(['status' => CreditCardPaymentStatus::PAID])->saveQuietly();
        $this->digest();
        $this->assertFalse(NotificationCondition::query()->where('subject_id', $overdue->id)->where('trigger', 'credit_card_payment')->firstOrFail()->active);
    }

    public function test_card_payment_escalates_when_it_becomes_overdue(): void
    {
        $card = $this->card($this->user, 5000, 0);
        $this->cardPayment($card, '2026-10-17');

        $this->digest('2026-10-17');
        $this->digest('2026-10-18');

        $this->assertSame(['critical', 'warning'], $this->notificationsFor($this->user)->pluck('severity')->sort()->values()->all());
    }

    public function test_loan_installments_window_overdue_paid_and_inactive_loans(): void
    {
        $edge = $this->loanPayment($this->user, '2026-10-20');
        $this->loanPayment($this->user, '2026-10-21');
        $overdue = $this->loanPayment($this->user, '2026-10-01');
        $this->loanPayment($this->user, '2026-10-18', LoanPaymentStatus::PAID);
        $this->loanPayment($this->user, '2026-10-18', loanStatus: 'completed');
        $this->loanPayment($this->other, '2026-10-18');

        $this->digest();
        $this->digest();

        $byId = $this->notificationsFor($this->user, 'loan_payment')->keyBy('related_id');
        $this->assertCount(2, $byId);
        $this->assertSame('warning', $byId[$edge->id]->severity);
        $this->assertSame('critical', $byId[$overdue->id]->severity);
        $this->assertCount(1, $this->notificationsFor($this->other));
    }

    public function test_subscription_renewal_window_status_and_date_advance_resolution(): void
    {
        $due = $this->subscription($this->user, '2026-10-20');
        $this->subscription($this->user, '2026-10-21');
        $this->subscription($this->user, '2026-10-16');
        $this->subscription($this->user, '2026-10-18', SubscriptionStatus::CANCELLED);
        $this->subscription($this->other, '2026-10-18');

        $this->digest();
        $this->digest();

        $this->assertCount(1, $this->notificationsFor($this->user, 'subscription_renewal'));
        $this->assertCount(1, $this->notificationsFor($this->other));

        $due->update(['next_renewal_date' => '2026-11-20']);
        $this->digest();

        $this->assertFalse(NotificationCondition::query()->where('subject_id', $due->id)->where('trigger', 'subscription_renewal')->firstOrFail()->active);
        $this->assertCount(1, $this->notificationsFor($this->user, 'subscription_renewal'));
    }

    public function test_a_failing_owner_does_not_stop_others_and_keeps_its_conditions(): void
    {
        $this->subscription($this->user, '2026-10-18');
        $this->subscription($this->other, '2026-10-18');
        $this->digest();

        $this->app->bind(FinanceNotificationEvaluator::class, fn () => new class(app(BudgetService::class)) extends FinanceNotificationEvaluator
        {
            public function evaluate(int $userId, CarbonInterface $date): array
            {
                if ($userId === User::query()->orderBy('id')->value('id')) {
                    throw new \RuntimeException('boom');
                }

                return parent::evaluate($userId, $date);
            }
        });

        Subscription::query()->withoutUserScope()->where('user_id', $this->other->id)->update(['next_renewal_date' => '2026-12-01']);

        $this->assertSame(1, $this->digest());

        $this->assertTrue(NotificationCondition::query()->where('user_id', $this->user->id)->firstOrFail()->active);
        $this->assertFalse(NotificationCondition::query()->where('user_id', $this->other->id)->firstOrFail()->active);
    }

    public function test_inactive_users_are_skipped(): void
    {
        $this->subscription($this->other, '2026-10-18');
        $this->other->forceFill(['is_active' => false])->saveQuietly();

        $this->digest();

        $this->assertCount(0, $this->notificationsFor($this->other));
    }

    public function test_retention_purges_only_old_read_notifications_and_keeps_conditions_closed(): void
    {
        $this->subscription($this->user, '2026-10-18');
        $this->digest();
        $condition = NotificationCondition::query()->where('user_id', $this->user->id)->firstOrFail();

        $make = fn (array $attrs) => Notification::query()->withoutUserScope()->create($attrs + [
            'user_id' => $this->user->id, 'type' => 'in_app', 'title' => 't', 'message' => 'm',
        ]);
        $old = $make(['read_at' => '2026-07-17 01:00:00']);
        $recent = $make(['read_at' => '2026-08-01 00:00:00']);
        $unread = $make(['read_at' => null]);
        Notification::query()->withoutUserScope()->where('notification_condition_id', $condition->id)->update(['read_at' => '2026-06-01 00:00:00']);

        $this->digest();

        $this->assertNull(Notification::query()->withoutUserScope()->withTrashed()->find($old->id));
        $this->assertNotNull(Notification::query()->withoutUserScope()->find($recent->id));
        $this->assertNotNull(Notification::query()->withoutUserScope()->find($unread->id));
        $this->assertSame(0, Notification::query()->withoutUserScope()->where('notification_condition_id', $condition->id)->count());
        $this->assertTrue($condition->fresh()->active);
        $this->assertSame(1, $condition->fresh()->episode);
    }

    public function test_digest_is_scheduled_once_after_the_finance_jobs(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'finance:notifications-digest'));

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('5 2 * * *', $event->expression);
        $this->assertTrue($event->withoutOverlapping);
    }

    public function test_service_runs_without_ambient_authentication(): void
    {
        $this->assertGuest();
        $this->subscription($this->user, '2026-10-18');

        $result = app(FinanceNotificationDigest::class)->runForUser($this->user->id, Carbon::parse('2026-10-17'));

        $this->assertSame(['created' => 1, 'resolved' => 0], $result);
    }
}
