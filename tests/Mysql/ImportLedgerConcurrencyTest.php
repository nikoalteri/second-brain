<?php

declare(strict_types=1);

namespace Tests\Mysql;

use App\Enums\ImportRowStatus;
use App\Models\Loan;
use App\Models\TransactionType;
use App\Services\Import\ImportRowPromotionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phase 25-05: the import's "re-check for duplicates, then insert" must be atomic against every other
 * writer of the same account. Each test freezes one writer at a known point (holding its lock) and starts
 * the other: it must WAIT, and once released the outcome must reflect what the first one committed.
 */
class ImportLedgerConcurrencyTest extends MysqlConcurrencyTestCase
{
    private const COFFEE = "Date,Amount,Description\n2026-10-01,-5.00,Coffee\n";

    public function test_a_manual_entry_that_is_mid_insert_makes_the_import_wait_and_then_hold_its_row_back(): void
    {
        $user = $this->user();
        $account = $this->account($user);
        $batch = $this->mappedBatch($user, $account, self::COFFEE);
        $row = $this->row($batch, 2);
        $this->assertSame('none', $row->duplicate_class, 'reviewed as clean');

        // Writer A: the manual entry is inserted and holds the account lock, but is not committed.
        $a = $this->spec(['action' => 'manual_create', 'user_id' => $user->id, 'account_id' => $account->id, 'amount' => -5.0, 'date' => '2026-10-01', 'description' => 'Coffee', 'pause_on' => 'end']);
        $processA = $this->start($a);
        $this->awaitFrozen($a, $processA);

        // Writer B: the import of the same charge. It must not get past the lock.
        $b = $this->spec(['action' => 'import_promote', 'user_id' => $user->id, 'batch_id' => $batch->id, 'row_id' => $row->id]);
        $processB = $this->start($b);
        $this->assertWaiting($processB);

        $this->release($a);
        $this->outcomeOf($processA);
        $outcome = $this->outcomeOf($processB);

        $this->assertSame('skipped', $outcome['outcome']);
        $this->assertStringContainsString('review it again', $outcome['reason']);
        $this->assertSame(1, $this->ledgerCount($account), 'one real charge, one ledger movement');
        $this->assertSame(995.0, $this->balance($account));
        $this->assertSame(ImportRowStatus::DRAFT, $this->row($batch, 2)->status);
    }

    public function test_an_import_between_its_check_and_its_insert_makes_a_manual_entry_wait(): void
    {
        $user = $this->user();
        $account = $this->account($user);
        $batch = $this->mappedBatch($user, $account, self::COFFEE);
        $row = $this->row($batch, 2);

        // Writer A: the import has passed its re-check and is about to insert, holding the lock.
        $a = $this->spec(['action' => 'import_promote', 'user_id' => $user->id, 'batch_id' => $batch->id, 'row_id' => $row->id, 'pause_on' => 'creating']);
        $processA = $this->start($a);
        $this->awaitFrozen($a, $processA);

        // Writer B: a manual entry. It is never refused, but cannot slip in between the check and the insert.
        $b = $this->spec(['action' => 'manual_create', 'user_id' => $user->id, 'account_id' => $account->id, 'amount' => -5.0, 'date' => '2026-10-01', 'description' => 'Coffee']);
        $processB = $this->start($b);
        $this->assertWaiting($processB);

        $this->release($a);
        $this->assertSame('confirmed', $this->outcomeOf($processA)['outcome']);
        $this->outcomeOf($processB);

        $this->assertSame(2, $this->ledgerCount($account), 'both are real entries made by the owner');
        $this->assertSame(990.0, $this->balance($account));
        $this->assertSame(ImportRowStatus::CONFIRMED, $this->row($batch, 2)->status);
    }

    public function test_two_imports_of_the_same_charge_are_serialised_and_the_second_is_held_back(): void
    {
        $user = $this->user();
        $account = $this->account($user);
        // Same charge in two statements, described differently so they do not look like the same file.
        $first = $this->mappedBatch($user, $account, self::COFFEE);
        $second = $this->mappedBatch($user, $account, "Date,Amount,Description\n2026-10-01,-5.00,Coffee Shop\n");
        $this->assertSame('none', $this->row($second, 2)->duplicate_class);

        $a = $this->spec(['action' => 'import_promote', 'user_id' => $user->id, 'batch_id' => $first->id, 'row_id' => $this->row($first, 2)->id, 'pause_on' => 'creating']);
        $processA = $this->start($a);
        $this->awaitFrozen($a, $processA);

        $b = $this->spec(['action' => 'import_promote', 'user_id' => $user->id, 'batch_id' => $second->id, 'row_id' => $this->row($second, 2)->id]);
        $processB = $this->start($b);
        $this->assertWaiting($processB);

        $this->release($a);
        $this->assertSame('confirmed', $this->outcomeOf($processA)['outcome']);
        $outcome = $this->outcomeOf($processB);

        $this->assertSame('skipped', $outcome['outcome']);
        $this->assertStringContainsString('review it again', $outcome['reason']);
        $this->assertSame(1, $this->ledgerCount($account));
        $this->assertSame(995.0, $this->balance($account));
    }

    public function test_the_same_staging_row_confirmed_twice_at_once_moves_the_balance_once(): void
    {
        $user = $this->user();
        $account = $this->account($user);
        $batch = $this->mappedBatch($user, $account, self::COFFEE);
        $rowId = $this->row($batch, 2)->id;

        $a = $this->spec(['action' => 'import_promote', 'user_id' => $user->id, 'batch_id' => $batch->id, 'row_id' => $rowId, 'pause_on' => 'creating']);
        $processA = $this->start($a);
        $this->awaitFrozen($a, $processA);

        $b = $this->spec(['action' => 'import_promote', 'user_id' => $user->id, 'batch_id' => $batch->id, 'row_id' => $rowId]);
        $processB = $this->start($b);
        $this->assertWaiting($processB);

        $this->release($a);
        $this->assertSame('confirmed', $this->outcomeOf($processA)['outcome']);
        $outcome = $this->outcomeOf($processB);

        $this->assertSame('skipped', $outcome['outcome']);
        $this->assertSame('The row is not a draft.', $outcome['reason']);
        $this->assertSame(1, $this->ledgerCount($account));
        $this->assertSame(995.0, $this->balance($account));
    }

    // ─── automatic postings ─────────────────────────────────────────────────────────────────

    /** @return array{0: \App\Models\LoanPayment, 1: string} the unposted payment and its due date */
    private function unpostedInstallment($user, $account): array
    {
        $loan = Loan::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id, 'name' => 'Car loan', 'start_date' => '2026-01-01',
            'withdrawal_day' => 1, 'total_installments' => 12, 'monthly_payment' => 50,
        ]);
        $payment = $loan->payments()->create(['due_date' => Carbon::tomorrow()->toDateString(), 'amount' => 50, 'status' => 'pending']);

        // Due yesterday, set without observers: the automatic posting has not happened yet.
        $due = Carbon::yesterday()->toDateString();
        DB::table('loan_payments')->where('id', $payment->id)->update(['due_date' => $due, 'status' => 'pending']);
        DB::table('transactions')->where('loan_payment_id', $payment->id)->delete();
        DB::table('accounts')->where('id', $account->id)->update(['balance' => 1000]);

        return [$payment->fresh(), $due];
    }

    public function test_an_automatic_posting_mid_insert_makes_the_import_wait_and_then_hold_its_row_back(): void
    {
        $user = $this->user();
        $account = $this->account($user);
        [$payment, $due] = $this->unpostedInstallment($user, $account);
        $batch = $this->mappedBatch($user, $account, "Date,Amount,Description\n{$due},-50.00,Loan installment payment - Car loan\n");
        $row = $this->row($batch, 2);
        $this->assertSame('none', $row->duplicate_class);

        $a = $this->spec(['action' => 'loan_posting', 'user_id' => $user->id, 'loan_payment_id' => $payment->id, 'pause_on' => 'creating']);
        $processA = $this->start($a);
        $this->awaitFrozen($a, $processA);

        $b = $this->spec(['action' => 'import_promote', 'user_id' => $user->id, 'batch_id' => $batch->id, 'row_id' => $row->id]);
        $processB = $this->start($b);
        $this->assertWaiting($processB);

        $this->release($a);
        $this->outcomeOf($processA);
        $outcome = $this->outcomeOf($processB);

        $this->assertSame('skipped', $outcome['outcome']);
        $this->assertSame(1, $this->ledgerCount($account));
        $this->assertSame(950.0, $this->balance($account));
    }

    public function test_an_import_between_its_check_and_its_insert_makes_an_automatic_posting_wait(): void
    {
        $user = $this->user();
        $account = $this->account($user);
        [$payment] = $this->unpostedInstallment($user, $account);
        $batch = $this->mappedBatch($user, $account, "Date,Amount,Description\n2026-10-01,-5.00,Coffee\n");

        $a = $this->spec(['action' => 'import_promote', 'user_id' => $user->id, 'batch_id' => $batch->id, 'row_id' => $this->row($batch, 2)->id, 'pause_on' => 'creating']);
        $processA = $this->start($a);
        $this->awaitFrozen($a, $processA);

        $b = $this->spec(['action' => 'loan_posting', 'user_id' => $user->id, 'loan_payment_id' => $payment->id]);
        $processB = $this->start($b);
        $this->assertWaiting($processB);

        $this->release($a);
        $this->assertSame('confirmed', $this->outcomeOf($processA)['outcome']);
        $this->outcomeOf($processB);

        $this->assertSame(2, $this->ledgerCount($account), 'the posting always happens; it just cannot interleave');
        $this->assertSame(945.0, $this->balance($account));
    }

    // ─── transfers ──────────────────────────────────────────────────────────────────────────

    public function test_opposite_transfers_cannot_deadlock_because_both_accounts_are_locked_in_one_order(): void
    {
        $user = $this->user();
        $one = $this->account($user);
        $two = $this->account($user);

        // A→B freezes right before its first leg is inserted, holding the locks it needs.
        $a = $this->spec(['action' => 'transfer', 'user_id' => $user->id, 'from' => $one->id, 'to' => $two->id, 'amount' => 10.0, 'date' => '2026-10-01', 'pause_on' => 'creating']);
        $processA = $this->start($a);
        $this->awaitFrozen($a, $processA);

        // B→A must queue behind it instead of grabbing the other account and waiting for the first.
        $b = $this->spec(['action' => 'transfer', 'user_id' => $user->id, 'from' => $two->id, 'to' => $one->id, 'amount' => 4.0, 'date' => '2026-10-01', 'pause_on' => 'creating']);
        $processB = $this->start($b);
        $this->assertWaiting($processB, $b);

        $this->release($a);
        $this->outcomeOf($processA);

        $this->awaitFrozen($b, $processB);
        $this->release($b);
        $this->outcomeOf($processB);

        $this->assertSame(994.0, $this->balance($one));
        $this->assertSame(1006.0, $this->balance($two));
    }

    // ─── what the lock must NOT change ──────────────────────────────────────────────────────

    public function test_two_identical_legitimate_charges_and_a_conscious_duplicate_still_post(): void
    {
        $user = $this->user();
        $account = $this->account($user);

        $twins = $this->mappedBatch($user, $account, "Date,Amount,Description\n2026-10-05,-3.00,Bus\n2026-10-05,-3.00,Bus\n");
        $outcomes = app(ImportRowPromotionService::class)->confirm($twins, null);
        $this->assertSame(['confirmed', 'confirmed'], array_column($outcomes, 'outcome'));
        $this->assertSame(2, $this->ledgerCount($account));

        // An existing entry, then an import of the same charge: held back once, then a conscious choice.
        $type = TransactionType::query()->firstOrCreate(['name' => 'Expense'], ['is_income' => false]);
        DB::table('transactions')->insert([
            'user_id' => $user->id, 'account_id' => $account->id, 'transaction_type_id' => $type->id, 'amount' => -5, 'date' => '2026-10-01',
            'description' => 'Coffee', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $coffee = $this->mappedBatch($user, $account, self::COFFEE);
        $rowId = $this->row($coffee, 2)->id;
        $this->assertSame('exact', $this->row($coffee, 2)->duplicate_class, 'seen as a duplicate at review');

        $this->assertSame('confirmed', app(ImportRowPromotionService::class)->promote($coffee, $rowId)['outcome'], 'flagged rows proceed when chosen');
        $this->assertSame(4, $this->ledgerCount($account));
    }
}
