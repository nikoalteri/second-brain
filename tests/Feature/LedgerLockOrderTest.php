<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\AccountTransferService;
use App\Services\Import\ImportRowPromotionService;
use App\Support\LedgerLock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * SQLite ignores FOR UPDATE, so these tests pin the ORDER of events that makes the lock effective:
 * the target lock must be requested before the ledger insert (and, for the import, before the first
 * read of the ledger). The real blocking behaviour is proven with two MySQL connections in
 * tests/Mysql.
 */
class LedgerLockOrderTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $events = [];

    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id, 'currency' => 'EUR', 'balance' => 1000]);
        $this->actingAs($this->user);

        $events = &$this->events;
        $spy = new class($events) extends LedgerLock
        {
            public function __construct(private array &$events) {}

            public function accounts(iterable $ids): void
            {
                $this->events[] = 'lock:accounts:' . collect($ids)->filter()->unique()->sort()->implode(',');
                parent::accounts($ids);
            }

            public function card(?int $cardId): void
            {
                $this->events[] = 'lock:card:' . $cardId;
                parent::card($cardId);
            }
        };
        $this->app->instance(LedgerLock::class, $spy);

        DB::listen(function ($query) use (&$events) {
            if (preg_match('/^insert into "(transactions|credit_card_expenses)"/', $query->sql, $m)) {
                $events[] = 'insert:' . $m[1];
            }

            // A plain read of the ledger, as the import's duplicate check does.
            if (preg_match('/^select .* from "transactions" where .*"date" between/', $query->sql)) {
                $events[] = 'read:ledger';
            }
        });
    }

    private function type(bool $income = false): TransactionType
    {
        return TransactionType::query()->firstOrCreate(['name' => $income ? 'Income' : 'Expense'], ['is_income' => $income]);
    }

    private function create(Account $account, float $amount = -10.0, string $date = '2026-10-01', string $description = 'x'): Transaction
    {
        return DB::transaction(fn () => Transaction::create([
            'user_id' => $this->user->id, 'account_id' => $account->id, 'transaction_type_id' => $this->type($amount > 0)->id,
            'amount' => $amount, 'date' => $date, 'description' => $description,
        ]));
    }

    private function index(string $event): int|false
    {
        return array_search($event, $this->events, true);
    }

    public function test_a_manual_movement_locks_its_account_before_the_insert(): void
    {
        $this->create($this->account);

        $this->assertSame(["lock:accounts:{$this->account->id}", 'insert:transactions'], array_values(array_filter(
            $this->events, fn ($e) => str_starts_with($e, 'lock:') || str_starts_with($e, 'insert:'),
        )));
    }

    public function test_a_transfer_locks_both_accounts_in_one_ascending_statement_before_either_leg(): void
    {
        $other = Account::factory()->create(['user_id' => $this->user->id]);
        [$low, $high] = [min($this->account->id, $other->id), max($this->account->id, $other->id)];

        // Both directions produce the same lock statement, so they can never wait on each other.
        app(AccountTransferService::class)->transfer($this->account, $other, 5.0, '2026-10-01');
        app(AccountTransferService::class)->transfer($other, $this->account, 5.0, '2026-10-02');

        $locks = array_values(array_filter($this->events, fn ($e) => str_starts_with($e, 'lock:accounts:') && str_contains($e, ',')));
        $this->assertSame(["lock:accounts:{$low},{$high}", "lock:accounts:{$low},{$high}"], $locks);
        $this->assertLessThan($this->index('insert:transactions'), $this->index("lock:accounts:{$low},{$high}"));
    }

    public function test_changing_amount_date_or_account_locks_but_a_description_edit_does_not(): void
    {
        $transaction = $this->create($this->account);
        $other = Account::factory()->create(['user_id' => $this->user->id]);
        $this->events = [];

        DB::transaction(fn () => $transaction->update(['description' => 'renamed']));
        $this->assertSame([], array_filter($this->events, fn ($e) => str_starts_with($e, 'lock:')));

        DB::transaction(fn () => $transaction->update(['amount' => -20.0]));
        $this->assertContains("lock:accounts:{$this->account->id}", $this->events);

        $this->events = [];
        DB::transaction(fn () => $transaction->update(['account_id' => $other->id]));
        $this->assertContains('lock:accounts:' . collect([$this->account->id, $other->id])->sort()->implode(','), $this->events);
    }

    public function test_restoring_a_deleted_movement_locks_its_account(): void
    {
        $transaction = $this->create($this->account);
        DB::transaction(fn () => $transaction->delete());
        $this->events = [];

        DB::transaction(fn () => $transaction->restore());

        $this->assertContains("lock:accounts:{$this->account->id}", $this->events);
    }

    // ─── the import takes the target lock before reading the ledger ───────────────────────────

    private function mappedBatch(array $target, string $csv): ImportBatch
    {
        $this->actingAs($this->user);
        $upload = \Illuminate\Http\UploadedFile::fake()->createWithContent('s.csv', $csv);

        $id = $this->postJson('/api/v1/import-batches', $target + ['file' => $upload])->assertCreated()->json('data.id');
        \Laravel\Sanctum\Sanctum::actingAs($this->user);
        $this->putJson("/api/v1/import-batches/{$id}/mapping", [
            'header_row' => 1, 'date_column' => 0, 'date_format' => 'Y-m-d', 'amount_mode' => 'single', 'amount_column' => 1,
            'decimal_separator' => '.', 'thousands_separator' => ',', 'description_column' => 2,
        ])->assertOk();

        return ImportBatch::findOrFail($id);
    }

    public function test_the_import_locks_the_account_before_its_final_ledger_check_and_the_insert(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->user);
        $batch = $this->mappedBatch(['account_id' => $this->account->id], "Date,Amount,Description\n2026-10-01,-5.00,Coffee\n");
        $row = ImportRow::where('import_batch_id', $batch->id)->where('row_number', 2)->firstOrFail();
        $this->events = [];

        $this->assertSame('confirmed', app(ImportRowPromotionService::class)->promote($batch, $row->id)['outcome']);

        $lock = $this->index("lock:accounts:{$this->account->id}");
        $this->assertNotFalse($lock);
        $this->assertLessThan($this->index('read:ledger'), $lock, 'no ledger read before the lock');
        $this->assertLessThan($this->index('insert:transactions'), $this->index('read:ledger'));
    }

    public function test_the_import_locks_the_card_before_posting_a_card_expense(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->user);
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $batch = $this->mappedBatch(['credit_card_id' => $card->id], "Date,Amount,Description\n2026-10-05,-8.00,Cafe\n");
        $row = ImportRow::where('import_batch_id', $batch->id)->where('row_number', 2)->firstOrFail();
        $this->events = [];

        $this->assertSame('confirmed', app(ImportRowPromotionService::class)->promote($batch, $row->id)['outcome']);

        $this->assertLessThan($this->index('insert:credit_card_expenses'), $this->index("lock:card:{$card->id}"));
    }

    public function test_a_row_reviewed_as_clean_is_held_back_when_a_match_appears_before_posting(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->user);
        $batch = $this->mappedBatch(['account_id' => $this->account->id], "Date,Amount,Description\n2026-10-01,-5.00,Coffee\n");
        $row = ImportRow::where('import_batch_id', $batch->id)->where('row_number', 2)->firstOrFail();
        $this->assertSame('none', $row->duplicate_class);

        // The race: the identical movement is committed after the review, and promote() is called
        // directly, with no confirm() pre-pass in front of it.
        $this->create($this->account, -5.00, '2026-10-01', 'Coffee');
        $before = Transaction::withoutGlobalScopes()->count();

        $result = app(ImportRowPromotionService::class)->promote($batch, $row->id);

        $this->assertSame('skipped', $result['outcome']);
        $this->assertStringContainsString('review it again', $result['reason']);
        $this->assertSame($before, Transaction::withoutGlobalScopes()->count());
        $row->refresh();
        $this->assertSame('exact', $row->duplicate_class);
        $this->assertSame('transaction', $row->flags['match']['type']);

        // Now the owner has seen it flagged: choosing it again is a conscious duplicate.
        $this->assertSame('confirmed', app(ImportRowPromotionService::class)->promote($batch, $row->id)['outcome']);
    }

    public function test_the_single_row_recheck_keeps_one_to_one_matching_and_identical_charges(): void
    {
        \Laravel\Sanctum\Sanctum::actingAs($this->user);
        $this->create($this->account, -3.00, '2026-10-05');
        $batch = $this->mappedBatch(['account_id' => $this->account->id], "Date,Amount,Description\n2026-10-05,-3.00,x\n2026-10-05,-3.00,x\n");
        $rows = ImportRow::where('import_batch_id', $batch->id)->whereIn('row_number', [2, 3])->orderBy('row_number')->get();

        // Reviewed state: the first row claims the existing movement, the second is a new charge.
        $this->assertSame(['exact', 'none'], $rows->pluck('duplicate_class')->all());

        $detector = app(\App\Services\Import\ImportDuplicateDetector::class);
        $this->assertNotNull($detector->matchOne($batch, $rows[0]));
        $this->assertNull($detector->matchOne($batch, $rows[1]), 'the movement is already claimed by the first row');
    }
}
