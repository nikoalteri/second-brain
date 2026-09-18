<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardExpense;
use App\Models\Loan;
use App\Models\SavingGoal;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\AccountTransferService;
use App\Services\DataIntegrityAuditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataIntegrityAuditTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;

    private User $userB;

    private Account $accountA;

    private Account $accountB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
        $this->accountA = Account::factory()->create(['user_id' => $this->userA->id, 'balance' => 0, 'opening_balance' => 0]);
        $this->accountB = Account::factory()->create(['user_id' => $this->userB->id, 'balance' => 0, 'opening_balance' => 0]);
    }

    /** @return array<string, array{count: int, ids: list<int|string>}> */
    private function report(): array
    {
        return app(DataIntegrityAuditor::class)->run();
    }

    private function expense(): TransactionType
    {
        return TransactionType::query()->firstOrCreate(['name' => 'Expenses'], ['is_income' => false]);
    }

    /** Built explicitly: TransactionFactory eagerly creates unrelated users, accounts and categories. */
    private function transactionFor(User $user, Account $account, float $amount = -10): Transaction
    {
        return Transaction::create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $this->expense()->id,
            'transaction_category_id' => null,
            'amount' => $amount,
            'date' => '2026-01-05',
            'description' => 'Test movement',
        ]);
    }

    private function accountFor(User $user): Account
    {
        return Account::factory()->create(['user_id' => $user->id, 'balance' => 0, 'opening_balance' => 0]);
    }

    public function test_data_produced_by_the_application_reports_no_issue(): void
    {
        $this->transactionFor($this->userA, $this->accountA, -25);
        app(AccountTransferService::class)->transfer($this->accountA, $this->accountFor($this->userA), 40, '2026-01-10');
        $card = CreditCard::factory()->create(['user_id' => $this->userA->id, 'account_id' => $this->accountA->id]);
        CreditCardExpense::factory()->create(['credit_card_id' => $card->id, 'credit_card_cycle_id' => null, 'amount' => 30]);
        Loan::factory()->create(['user_id' => $this->userA->id, 'account_id' => $this->accountA->id]);

        $issues = array_filter($this->report(), fn (array $check) => $check['count'] > 0);

        $this->assertSame([], array_keys($issues), json_encode($issues));
    }

    public function test_cross_user_references_are_reported(): void
    {
        $transaction = $this->transactionFor($this->userA, $this->accountA);
        DB::table('transactions')->where('id', $transaction->id)->update(['user_id' => $this->userB->id]);

        $categoryOfB = TransactionCategory::create(['user_id' => $this->userB->id, 'name' => 'Private', 'is_active' => true]);
        $withCategory = $this->transactionFor($this->userA, $this->accountA);
        DB::table('transactions')->where('id', $withCategory->id)->update(['transaction_category_id' => $categoryOfB->id]);

        $withTarget = $this->transactionFor($this->userA, $this->accountA);
        DB::table('transactions')->where('id', $withTarget->id)->update(['to_account_id' => $this->accountB->id]);

        $loan = Loan::factory()->create(['user_id' => $this->userA->id, 'account_id' => $this->accountB->id]);
        $card = CreditCard::factory()->create(['user_id' => $this->userA->id, 'account_id' => $this->accountB->id]);
        $goal = SavingGoal::factory()->create(['user_id' => $this->userA->id, 'account_id' => $this->accountB->id]);

        $frequency = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();
        $subscription = Subscription::factory()->create([
            'user_id' => $this->userA->id,
            'account_id' => $this->accountB->id,
            'category_id' => $categoryOfB->id,
            'subscription_frequency_id' => $frequency->id,
        ]);

        $report = $this->report();

        $this->assertSame([$transaction->id], $report['transactions_on_foreign_account']['ids']);
        $this->assertSame([$withCategory->id], $report['transactions_with_foreign_category']['ids']);
        $this->assertSame([$withTarget->id], $report['transactions_to_foreign_account']['ids']);
        $this->assertSame([$loan->id], $report['loans_on_foreign_account']['ids']);
        $this->assertSame([$card->id], $report['credit_cards_on_foreign_account']['ids']);
        $this->assertSame([$goal->id], $report['saving_goals_on_foreign_account']['ids']);
        $this->assertSame([$subscription->id], $report['subscriptions_on_foreign_account']['ids']);
        $this->assertSame([$subscription->id], $report['subscriptions_with_foreign_category']['ids']);
    }

    public function test_live_rows_on_deleted_accounts_are_reported(): void
    {
        $account = $this->accountFor($this->userA);
        $transaction = $this->transactionFor($this->userA, $account);
        $loan = Loan::factory()->create(['user_id' => $this->userA->id, 'account_id' => $account->id]);
        $card = CreditCard::factory()->create(['user_id' => $this->userA->id, 'account_id' => $account->id]);

        DB::table('accounts')->where('id', $account->id)->update(['deleted_at' => now()]);

        $report = $this->report();

        $this->assertSame([$transaction->id], $report['transactions_on_deleted_account']['ids']);
        $this->assertSame([$loan->id], $report['loans_on_deleted_account']['ids']);
        $this->assertSame([$card->id], $report['credit_cards_on_deleted_account']['ids']);
    }

    public function test_broken_transfers_are_reported(): void
    {
        $service = app(AccountTransferService::class);
        $third = $this->accountFor($this->userA);

        $single = $service->transfer($this->accountA, $third, 10, '2026-01-01');
        DB::table('transactions')->where('id', $single['in']->id)->update(['deleted_at' => now()]);

        $unbalanced = $service->transfer($this->accountA, $third, 20, '2026-01-02');
        DB::table('transactions')->where('id', $unbalanced['in']->id)->update(['amount' => 25]);

        $mismatch = $service->transfer($this->accountA, $third, 30, '2026-01-03');
        DB::table('transactions')->where('id', $mismatch['out']->id)->update(['to_account_id' => $this->accountB->id]);

        $extra = $service->transfer($this->accountA, $third, 40, '2026-01-04');
        DB::table('transactions')->where('id', $extra['in']->id)->update(['transfer_direction' => 'in']);
        DB::table('transactions')->insert([
            'user_id' => $this->userA->id, 'account_id' => $third->id, 'transaction_type_id' => $extra['in']->transaction_type_id,
            'amount' => 40, 'date' => '2026-01-04', 'description' => 'Extra', 'is_transfer' => true,
            'transfer_pair_id' => $extra['in']->transfer_pair_id, 'transfer_direction' => 'in',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $unpaired = $this->transactionFor($this->userA, $this->accountA);
        DB::table('transactions')->where('id', $unpaired->id)->update(['is_transfer' => true]);

        $report = $this->report();

        $this->assertSame([$single['out']->transfer_pair_id], $report['transfer_pairs_with_one_leg']['ids']);
        $this->assertSame([$unbalanced['out']->transfer_pair_id], $report['transfer_pairs_unbalanced']['ids']);
        $this->assertSame([$mismatch['out']->transfer_pair_id], $report['transfer_pairs_target_mismatch']['ids']);
        $this->assertSame([$extra['out']->transfer_pair_id], $report['transfer_pairs_with_extra_legs']['ids']);
        $this->assertSame([$unpaired->id], $report['transfer_legs_without_pair']['ids']);
    }

    public function test_balance_drift_is_reported_with_its_size(): void
    {
        $this->transactionFor($this->userA, $this->accountA, -25);
        DB::table('accounts')->where('id', $this->accountA->id)->update(['balance' => 100]);

        $card = CreditCard::factory()->create(['user_id' => $this->userA->id, 'account_id' => $this->accountA->id]);
        CreditCardExpense::factory()->create(['credit_card_id' => $card->id, 'credit_card_cycle_id' => null, 'amount' => 30]);
        DB::table('credit_cards')->where('id', $card->id)->update(['current_balance' => 500]);

        $report = $this->report();

        $this->assertSame(["{$this->accountA->id} (off by 125)"], $report['account_balance_drift']['ids']);
        $this->assertSame(["{$card->id} (off by 470)"], $report['credit_card_balance_drift']['ids']);
    }

    public function test_the_audit_only_reads(): void
    {
        $this->transactionFor($this->userA, $this->accountA);
        $writes = [];

        DB::listen(function ($query) use (&$writes) {
            if (! str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $writes[] = $query->sql;
            }
        });

        $this->report();

        $this->assertSame([], $writes);
    }

    public function test_the_command_fails_when_something_is_wrong_and_lists_identifiers_only(): void
    {
        $transaction = $this->transactionFor($this->userA, $this->accountA);
        DB::table('transactions')->where('id', $transaction->id)->update(['user_id' => $this->userB->id, 'description' => 'SECRET NOTE']);

        $this->artisan('data:audit')
            ->expectsOutputToContain('transactions_on_foreign_account: 1')
            ->doesntExpectOutputToContain('SECRET NOTE')
            ->assertFailed();
    }

    public function test_the_command_succeeds_on_clean_data(): void
    {
        $this->artisan('data:audit')->expectsOutputToContain('No inconsistencies found.')->assertSuccessful();
    }
}
