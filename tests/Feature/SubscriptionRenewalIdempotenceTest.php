<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\DataIntegrityAuditor;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class SubscriptionRenewalIdempotenceTest extends TestCase
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
        $this->account = Account::factory()->create(['user_id' => $this->user->id, 'balance' => 100, 'opening_balance' => 100]);
        $this->subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'subscription_frequency_id' => SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail()->id,
            'annual_cost' => 10,
            'monthly_cost' => 10,
            'day_of_month' => 23,
            'next_renewal_date' => '2026-04-23',
            'auto_create_transaction' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function transactionRow(string $renewalDate, ?int $subscriptionId): array
    {
        return [
            'user_id' => $this->user->id,
            'account_id' => $this->account->id,
            'transaction_type_id' => TransactionType::query()->firstOrCreate(['name' => 'Expense'], ['is_income' => false])->id,
            'subscription_id' => $subscriptionId,
            'subscription_renewal_date' => $renewalDate,
            'amount' => -10,
            'date' => $renewalDate,
            'description' => 'Renewal',
            'is_transfer' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    public function test_the_same_renewal_cannot_be_stored_twice_for_a_transaction(): void
    {
        DB::table('transactions')->insert($this->transactionRow('2026-04-23', $this->subscription->id));

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('transactions')->insert($this->transactionRow('2026-04-23', $this->subscription->id));
    }

    public function test_a_soft_deleted_renewal_still_blocks_a_duplicate(): void
    {
        $row = $this->transactionRow('2026-04-23', $this->subscription->id) + ['deleted_at' => now()];
        DB::table('transactions')->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('transactions')->insert($this->transactionRow('2026-04-23', $this->subscription->id));
    }

    public function test_the_same_renewal_cannot_be_stored_twice_for_a_card_expense(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $row = ['credit_card_id' => $card->id, 'subscription_id' => $this->subscription->id, 'subscription_renewal_date' => '2026-04-23',
            'spent_at' => '2026-04-23', 'amount' => 10, 'description' => 'Renewal', 'created_at' => now(), 'updated_at' => now()];

        DB::table('credit_card_expenses')->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('credit_card_expenses')->insert($row);
    }

    public function test_other_renewals_and_unrelated_rows_are_not_blocked(): void
    {
        DB::table('transactions')->insert($this->transactionRow('2026-04-23', $this->subscription->id));
        DB::table('transactions')->insert($this->transactionRow('2026-05-23', $this->subscription->id));
        DB::table('transactions')->insert($this->transactionRow('2026-04-23', null));
        DB::table('transactions')->insert($this->transactionRow('2026-04-23', null));

        $this->assertSame(4, DB::table('transactions')->count());
    }

    public function test_processing_the_same_renewal_twice_posts_it_once(): void
    {
        $service = app(SubscriptionService::class);

        $service->processRenewal($this->subscription->fresh(), Carbon::parse('2026-04-23'));
        $service->processRenewal($this->subscription->fresh(), Carbon::parse('2026-04-23'));

        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->count());
        $this->assertEquals(90, $this->account->fresh()->balance);
    }

    public function test_a_renewal_inserted_by_a_concurrent_run_is_reused_instead_of_failing(): void
    {
        $injected = false;
        Transaction::creating(function (Transaction $transaction) use (&$injected) {
            if (! $injected && $transaction->subscription_id) {
                $injected = true;
                // A concurrent run wins the race between the lookup and the insert. The date uses the
                // format Eloquent writes: SQLite stores dates as text, so '2026-04-23' would not
                // collide with '2026-04-23 00:00:00' (MySQL DATE columns treat them as equal).
                DB::table('transactions')->insert($this->transactionRow('2026-04-23 00:00:00', $this->subscription->id));
            }
        });

        $processed = app(SubscriptionService::class)->processRenewal($this->subscription->fresh(), Carbon::parse('2026-04-23'));

        $this->assertTrue($processed);
        $this->assertSame(1, Transaction::withoutGlobalScopes()->where('subscription_id', $this->subscription->id)->count());
        $this->assertSame('2026-05-23', $this->subscription->fresh()->next_renewal_date->toDateString());
    }

    public function test_the_migration_refuses_to_run_over_existing_duplicates(): void
    {
        Schema::table('transactions', fn ($table) => $table->dropUnique('transactions_subscription_renewal_unique'));
        DB::table('transactions')->insert($this->transactionRow('2026-04-23', $this->subscription->id));
        DB::table('transactions')->insert($this->transactionRow('2026-04-23', $this->subscription->id));

        $migration = require database_path('migrations/2026_09_19_110000_make_subscription_renewal_indexes_unique.php');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('transactions');

        $migration->up();
    }

    public function test_the_data_audit_lists_renewals_posted_twice(): void
    {
        Schema::table('transactions', fn ($table) => $table->dropUnique('transactions_subscription_renewal_unique'));
        DB::table('transactions')->insert($this->transactionRow('2026-04-23', $this->subscription->id));
        DB::table('transactions')->insert($this->transactionRow('2026-04-23', $this->subscription->id));

        $report = app(DataIntegrityAuditor::class)->run();

        $this->assertSame(["{$this->subscription->id}@2026-04-23 (x2)"], $report['transaction_renewals_duplicated']['ids']);
        $this->assertSame(0, $report['card_expense_renewals_duplicated']['count']);
    }
}
