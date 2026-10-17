<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Forecast;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\Forecast\ForecastObligation;
use App\Services\Forecast\ForecastObligationCollector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ForecastObligationCollectorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-17 10:00:00');
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id, 'currency' => 'EUR', 'balance' => 1000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, ForecastObligation> keyed by id */
    private function collect(?User $user = null): array
    {
        $this->actingAs($user ?? $this->user);

        $items = app(ForecastObligationCollector::class)->collect($user ?? $this->user, now());

        return collect($items)->keyBy(fn (ForecastObligation $o) => $o->id())->all();
    }

    private function loanPayment(string $due, string $status = 'pending', ?Account $account = null, ?User $owner = null): LoanPayment
    {
        $owner ??= $this->user;
        $loan = Loan::factory()->create([
            'user_id' => $owner->id,
            'account_id' => ($account ?? $this->account)->id,
            'start_date' => '2026-10-01',
            'withdrawal_day' => 1,
            'total_installments' => 2,
            'monthly_payment' => 250,
        ]);

        $payment = $loan->payments()->create(['due_date' => $due, 'amount' => 250, 'status' => $status]);
        // Observers re-sync status and postings on save: pin the fixture afterwards.
        DB::table('loan_payments')->where('id', $payment->id)->update(['status' => $status]);

        return $payment->fresh();
    }

    private function subscription(array $overrides = []): Subscription
    {
        $monthly = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();

        $subscription = Subscription::factory()->create($overrides + [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'credit_card_id' => null,
            'subscription_frequency_id' => $monthly->id,
            'day_of_month' => 20,
            'next_renewal_date' => '2026-10-20',
            'auto_create_transaction' => false,
            'status' => 'active',
        ]);
        DB::table('subscriptions')->where('id', $subscription->id)->update([
            'monthly_cost' => 10, 'annual_cost' => 10,
            'next_renewal_date' => $overrides['next_renewal_date'] ?? '2026-10-20',
        ]);

        return $subscription->fresh();
    }

    private function postRenewal(Subscription $subscription, string $date): Transaction
    {
        $type = TransactionType::query()->firstOrCreate(['name' => 'Expense'], ['is_income' => false]);

        return Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'transaction_type_id' => $type->id,
            'subscription_id' => $subscription->id,
            'subscription_renewal_date' => $date,
            'amount' => -10,
            'date' => $date,
            'description' => 'renewal',
        ]);
    }

    public function test_pending_unposted_loan_installment_counts_its_full_amount(): void
    {
        $payment = $this->loanPayment('2026-10-25');

        $item = $this->collect()["loan:{$payment->id}:2026-10-25"];

        $this->assertSame(25000, $item->cashOutflowMinor);
        $this->assertSame(ForecastObligation::STATE_UNPOSTED, $item->postingState);
        $this->assertNull($item->reason);
        $this->assertSame('EUR', $item->currency);
        $this->assertFalse($item->overdue);
    }

    public function test_pending_loan_installment_already_posted_contributes_zero(): void
    {
        $payment = $this->loanPayment('2026-10-17');
        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'transaction_type_id' => TransactionType::query()->firstOrCreate(['name' => 'Loan installment payment'], ['is_income' => false])->id,
            'loan_payment_id' => $payment->id,
            'amount' => -250,
            'date' => '2026-10-17',
            'description' => 'posting',
        ]);

        $item = $this->collect()["loan:{$payment->id}:2026-10-17"];

        $this->assertSame(0, $item->cashOutflowMinor);
        $this->assertSame(25000, $item->faceMinor);
        $this->assertSame(ForecastObligation::REASON_ALREADY_IN_BALANCE, $item->reason);
    }

    public function test_soft_deleted_loan_posting_does_not_suppress_the_deduction_but_flags_a_gap(): void
    {
        $payment = $this->loanPayment('2026-10-18');
        $posting = Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'transaction_type_id' => TransactionType::query()->firstOrCreate(['name' => 'Loan installment payment'], ['is_income' => false])->id,
            'loan_payment_id' => $payment->id,
            'amount' => -250,
            'date' => '2026-10-18',
            'description' => 'posting',
        ]);
        $posting->delete();

        $item = $this->collect()["loan:{$payment->id}:2026-10-18"];

        $this->assertSame(25000, $item->cashOutflowMinor);
        $this->assertSame(ForecastObligation::REASON_POSTING_SOFT_DELETED, $item->reason);
        $this->assertTrue($item->isCoverageGap());
    }

    public function test_window_is_inclusive_of_day_thirty_and_excludes_day_thirty_one(): void
    {
        $inside = $this->loanPayment('2026-11-16');
        $outside = $this->loanPayment('2026-11-17');

        $items = $this->collect();

        $this->assertArrayHasKey("loan:{$inside->id}:2026-11-16", $items);
        $this->assertArrayNotHasKey("loan:{$outside->id}:2026-11-17", $items);
    }

    public function test_overdue_unposted_installment_is_listed_with_zero_cash_and_a_gap(): void
    {
        $payment = $this->loanPayment('2026-10-10');
        // The observer posts overdue installments on save; remove it without touching balances.
        DB::table('transactions')->where('loan_payment_id', $payment->id)->delete();

        $item = $this->collect()["loan:{$payment->id}:2026-10-10"];

        $this->assertTrue($item->overdue);
        $this->assertSame(0, $item->cashOutflowMinor);
        $this->assertSame(ForecastObligation::REASON_OVERDUE, $item->reason);
        $this->assertTrue($item->isCoverageGap());
    }

    public function test_overdue_installment_already_reflected_in_balance_is_not_listed(): void
    {
        // Saving an overdue installment makes the observer post it, so the balance already moved.
        $payment = $this->loanPayment('2026-10-10');
        $this->assertTrue(Transaction::where('loan_payment_id', $payment->id)->exists());

        $this->assertArrayNotHasKey("loan:{$payment->id}:2026-10-10", $this->collect());
    }

    public function test_paid_installments_are_ignored(): void
    {
        $payment = $this->loanPayment('2026-10-20', 'paid');

        $this->assertArrayNotHasKey("loan:{$payment->id}:2026-10-20", $this->collect());
    }

    public function test_card_payment_counts_total_amount_and_card_renewal_is_never_a_cash_debit(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $payment = CreditCardPayment::create([
            'credit_card_id' => $card->id,
            'due_date' => '2026-10-28',
            'installment_amount' => 148, 'interest_amount' => 0, 'principal_amount' => 148,
            'stamp_duty_amount' => 2, 'total_amount' => 150, 'status' => 'pending',
        ]);
        $subscription = $this->subscription(['account_id' => null, 'credit_card_id' => $card->id]);
        CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'subscription_id' => $subscription->id,
            'spent_at' => '2026-10-05', 'posted_at' => '2026-10-05',
            'subscription_renewal_date' => '2026-10-05',
            'amount' => 10, 'description' => 'earlier renewal',
        ]);

        $items = $this->collect();

        $this->assertSame(15000, $items["credit-card:{$payment->id}:2026-10-28"]->cashOutflowMinor);

        $renewal = $items["subscription:{$subscription->id}:2026-10-20"];
        $this->assertSame(0, $renewal->cashOutflowMinor);
        $this->assertSame(ForecastObligation::STATE_CARD_CHARGE, $renewal->postingState);
        $this->assertSame(ForecastObligation::REASON_CARD_CHARGE_PROJECTED, $renewal->reason);
        $this->assertSame('EUR', $renewal->currency, 'card renewals take the settlement account currency');
        $this->assertSame(0, collect($items)->where('type', 'subscription')->sum('cashOutflowMinor'));
    }

    public function test_monthly_subscription_projects_two_occurrences_inside_thirty_days(): void
    {
        // February is short enough for two monthly renewals to fit the inclusive 30-day window.
        Carbon::setTestNow('2027-02-01 10:00:00');
        $subscription = $this->subscription(['next_renewal_date' => '2027-02-01', 'day_of_month' => 1]);

        $items = collect($this->collect())->where('sourceId', $subscription->id)->where('type', 'subscription');

        $this->assertSame(['2027-02-01', '2027-03-01'], $items->pluck('dueDate')->values()->all());
        $this->assertSame(2000, $items->sum('cashOutflowMinor'));
        $this->assertCount(2, $items->map(fn (ForecastObligation $o) => $o->id())->unique());
    }

    public function test_posted_account_renewal_on_same_date_contributes_zero_and_is_not_duplicated(): void
    {
        $subscription = $this->subscription();
        $this->postRenewal($subscription, '2026-10-20');

        $items = collect($this->collect())->where('type', 'subscription');

        $this->assertCount(1, $items);
        $this->assertSame(0, $items->first()->cashOutflowMinor);
        $this->assertSame(1000, $items->first()->faceMinor);
        $this->assertSame(ForecastObligation::REASON_ALREADY_IN_BALANCE, $items->first()->reason);
    }

    public function test_soft_deleted_renewal_posting_still_counts_and_is_flagged(): void
    {
        $subscription = $this->subscription();
        $this->postRenewal($subscription, '2026-10-20')->delete();

        $item = $this->collect()["subscription:{$subscription->id}:2026-10-20"];

        $this->assertSame(1000, $item->cashOutflowMinor);
        $this->assertSame(ForecastObligation::REASON_POSTING_SOFT_DELETED, $item->reason);
    }

    public function test_every_missed_subscription_renewal_is_listed_as_overdue(): void
    {
        $subscription = $this->subscription(['next_renewal_date' => '2026-07-20']);

        $items = collect($this->collect())->where('type', 'subscription');
        $overdue = $items->where('overdue', true);

        $this->assertSame(['2026-07-20', '2026-08-20', '2026-09-20'], $overdue->pluck('dueDate')->values()->all());
        $this->assertSame(0, $overdue->sum('cashOutflowMinor'));
        $this->assertSame([ForecastObligation::REASON_OVERDUE], $overdue->pluck('reason')->unique()->values()->all());
        $this->assertSame(1000, $items->where('overdue', false)->sum('cashOutflowMinor'));
        $this->assertSame($subscription->id, $overdue->first()->sourceId);
    }

    public function test_paid_loan_installment_without_posting_raises_a_coverage_warning(): void
    {
        $payment = $this->loanPayment('2026-10-12', 'paid');
        $this->assertTrue(Transaction::where('loan_payment_id', $payment->id)->exists(), 'sanity: paid rows are posted');
        $this->assertArrayNotHasKey("loan:{$payment->id}:2026-10-12", $this->collect(), 'paid with posting is settled');

        DB::table('transactions')->where('loan_payment_id', $payment->id)->delete();
        $item = $this->collect()["loan:{$payment->id}:2026-10-12"];

        $this->assertSame(ForecastObligation::REASON_PAID_WITHOUT_POSTING, $item->reason);
        $this->assertSame(0, $item->cashOutflowMinor);
        $this->assertTrue($item->isCoverageGap());
    }

    public function test_paid_loan_installment_with_soft_deleted_posting_raises_a_coverage_warning(): void
    {
        $payment = $this->loanPayment('2026-10-12', 'paid');
        Transaction::where('loan_payment_id', $payment->id)->firstOrFail()->delete();

        $item = $this->collect()["loan:{$payment->id}:2026-10-12"];

        $this->assertSame(ForecastObligation::REASON_PAID_WITHOUT_POSTING, $item->reason);
        $this->assertSame(ForecastObligation::STATE_POSTING_TRASHED, $item->postingState);
    }

    public function test_paid_card_payment_without_posting_raises_a_coverage_warning_only_when_missing(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $payment = CreditCardPayment::create([
            'credit_card_id' => $card->id, 'due_date' => '2026-10-14',
            'installment_amount' => 100, 'interest_amount' => 0, 'principal_amount' => 100,
            'stamp_duty_amount' => 0, 'total_amount' => 100, 'status' => 'pending',
        ]);
        DB::table('credit_card_payments')->where('id', $payment->id)->update(['status' => 'paid']);

        $item = $this->collect()["credit-card:{$payment->id}:2026-10-14"];
        $this->assertSame(ForecastObligation::REASON_PAID_WITHOUT_POSTING, $item->reason);
        $this->assertSame(0, $item->cashOutflowMinor);

        Transaction::create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'transaction_type_id' => TransactionType::query()->firstOrCreate(['name' => 'Credit Card payment'], ['is_income' => false])->id,
            'credit_card_payment_id' => $payment->id,
            'amount' => -100, 'date' => '2026-10-14', 'description' => 'posting',
        ]);

        $this->assertArrayNotHasKey("credit-card:{$payment->id}:2026-10-14", $this->collect());
    }

    public function test_old_paid_rows_outside_the_lookback_are_ignored(): void
    {
        $payment = $this->loanPayment('2026-05-01', 'paid');
        DB::table('transactions')->where('loan_payment_id', $payment->id)->delete();

        $this->assertArrayNotHasKey("loan:{$payment->id}:2026-05-01", $this->collect());
    }

    public function test_recently_paid_loan_with_old_due_date_and_no_posting_is_flagged(): void
    {
        $payment = $this->loanPayment('2026-05-01', 'paid');
        DB::table('loan_payments')->where('id', $payment->id)->update(['actual_date' => '2026-10-16']);
        DB::table('transactions')->where('loan_payment_id', $payment->id)->delete();

        $item = $this->collect()["loan:{$payment->id}:2026-05-01"];
        $this->assertSame(ForecastObligation::REASON_PAID_WITHOUT_POSTING, $item->reason);
        $this->assertSame(0, $item->cashOutflowMinor);
    }

    public function test_recently_paid_card_payment_with_old_due_date_and_no_posting_is_flagged(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $payment = CreditCardPayment::create([
            'credit_card_id' => $card->id, 'due_date' => '2026-05-01',
            'installment_amount' => 100, 'interest_amount' => 0, 'principal_amount' => 100,
            'stamp_duty_amount' => 0, 'total_amount' => 100, 'status' => 'pending',
        ]);
        DB::table('credit_card_payments')->where('id', $payment->id)->update([
            'status' => 'paid', 'actual_date' => '2026-10-16',
        ]);

        $item = $this->collect()["credit-card:{$payment->id}:2026-05-01"];
        $this->assertSame(ForecastObligation::REASON_PAID_WITHOUT_POSTING, $item->reason);
        $this->assertSame(0, $item->cashOutflowMinor);
    }

    public function test_renewal_posted_on_card_path_after_source_switch_is_not_a_cash_debit(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $subscription = $this->subscription();
        CreditCardExpense::create([
            'credit_card_id' => $card->id,
            'subscription_id' => $subscription->id,
            'spent_at' => '2026-10-20', 'posted_at' => '2026-10-20',
            'subscription_renewal_date' => '2026-10-20',
            'amount' => 10, 'description' => 'switched',
        ]);

        $item = $this->collect()["subscription:{$subscription->id}:2026-10-20"];

        $this->assertSame(0, $item->cashOutflowMinor);
        $this->assertSame(ForecastObligation::REASON_CARD_CHARGE_RECORDED, $item->reason);
    }

    public function test_unknown_currency_and_missing_source_are_explicit_and_never_guessed(): void
    {
        $noCurrency = Account::factory()->create(['user_id' => $this->user->id, 'currency' => '']);
        $a = $this->loanPayment('2026-10-21', 'pending', $noCurrency);
        $orphan = $this->subscription(['account_id' => null, 'credit_card_id' => null]);

        $items = $this->collect();

        $this->assertSame(ForecastObligation::REASON_UNKNOWN_CURRENCY, $items["loan:{$a->id}:2026-10-21"]->reason);
        $this->assertNull($items["loan:{$a->id}:2026-10-21"]->currency);
        $this->assertSame(0, $items["loan:{$a->id}:2026-10-21"]->cashOutflowMinor);
        $this->assertSame(ForecastObligation::REASON_SOURCE_UNAVAILABLE, $items["subscription:{$orphan->id}:2026-10-20"]->reason);
    }

    public function test_foreign_source_account_is_treated_as_unavailable(): void
    {
        $stranger = User::factory()->create();
        $foreignAccount = Account::factory()->create(['user_id' => $stranger->id, 'currency' => 'USD']);
        $payment = $this->loanPayment('2026-10-21', 'pending', $foreignAccount);

        $item = $this->collect()["loan:{$payment->id}:2026-10-21"];

        $this->assertSame(ForecastObligation::REASON_SOURCE_UNAVAILABLE, $item->reason);
        $this->assertNull($item->currency);
    }

    public function test_other_users_rows_never_enter_the_result_even_for_a_superadmin(): void
    {
        $stranger = User::factory()->create();
        $strangerAccount = Account::factory()->create(['user_id' => $stranger->id]);
        $this->loanPayment('2026-10-21', 'pending', $strangerAccount, $stranger);

        Role::findOrCreate('superadmin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');

        $this->assertSame([], $this->collect($admin));
        $this->assertSame([], $this->collect());
    }

    public function test_inactive_subscription_is_not_projected(): void
    {
        $this->subscription(['status' => 'cancelled']);

        $this->assertSame([], collect($this->collect())->where('type', 'subscription')->all());
    }
}
