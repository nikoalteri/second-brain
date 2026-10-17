<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ImportRowStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportMappingApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/import-batches';

    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id, 'currency' => 'EUR', 'balance' => 1000]);
        Sanctum::actingAs($this->user);
    }

    private function upload(string $csv, array $target = []): int
    {
        return $this->post(self::URL, ($target ?: ['account_id' => $this->account->id]) + [
            'file' => UploadedFile::fake()->createWithContent('s.csv', $csv),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    }

    private function mapping(array $overrides = []): array
    {
        return $overrides + [
            'header_row' => 1, 'date_column' => 0, 'date_format' => 'Y-m-d',
            'amount_mode' => 'single', 'amount_column' => 1,
            'decimal_separator' => '.', 'thousands_separator' => ',',
            'description_column' => 2,
        ];
    }

    private function map(int $id, array $overrides = [])
    {
        return $this->putJson(self::URL . "/{$id}/mapping", $this->mapping($overrides));
    }

    private function row(int $batchId, int $number): ImportRow
    {
        return ImportRow::where('import_batch_id', $batchId)->where('row_number', $number)->firstOrFail();
    }

    private function ledgerTransaction(array $attrs, bool $income = false): Transaction
    {
        // The model forces the sign from the type, so the type must agree with the amount.
        $type = TransactionType::query()->firstOrCreate(
            ['name' => $income ? 'Income' : 'Expense'],
            ['is_income' => $income],
        );

        return Transaction::create($attrs + [
            'user_id' => $this->user->id, 'account_id' => $this->account->id,
            'transaction_type_id' => $type->id, 'description' => 'Ledger row',
        ]);
    }

    public function test_mapping_normalises_rows_skips_the_header_and_summarises(): void
    {
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-12.50,Coffee   Shop\n2026-10-02,1500.00,Salary\n");

        $response = $this->map($id)->assertOk();

        $response->assertJsonPath('summary.rows', 3)
            ->assertJsonPath('summary.skipped', 1)
            ->assertJsonPath('summary.ready', 2)
            ->assertJsonPath('summary.errors', 0)
            ->assertJsonPath('data.status', 'mapped')
            ->assertJsonPath('data.mapping_version', 1);

        $this->assertSame(ImportRowStatus::SKIPPED, $this->row($id, 1)->status);
        $this->assertSame(
            ['date' => '2026-10-01', 'amount_minor' => -1250, 'description' => 'Coffee Shop', 'category' => null, 'currency' => null, 'category_id' => null],
            $this->row($id, 2)->normalized,
        );
        $this->assertSame(150000, $this->row($id, 3)->normalized['amount_minor']);
    }

    public function test_european_formats_inverted_sign_and_split_columns(): void
    {
        $id = $this->upload("Data;Dare;Avere;Descrizione\n01/10/2026;12,50;;Caffe\n02/10/2026;;1.500,00;Stipendio\n");
        $this->map($id, [
            'date_format' => 'd/m/Y', 'amount_mode' => 'split', 'debit_column' => 1, 'credit_column' => 2,
            'decimal_separator' => ',', 'thousands_separator' => '.', 'description_column' => 3,
        ])->assertOk()->assertJsonPath('summary.ready', 2);

        $this->assertSame(-1250, $this->row($id, 2)->normalized['amount_minor']);
        $this->assertSame(150000, $this->row($id, 3)->normalized['amount_minor']);

        $this->map($id, [
            'date_format' => 'd/m/Y', 'amount_mode' => 'split', 'debit_column' => 1, 'credit_column' => 2,
            'decimal_separator' => ',', 'thousands_separator' => '.', 'description_column' => 3, 'invert_sign' => true,
        ])->assertOk();
        $this->assertSame(1250, $this->row($id, 2)->normalized['amount_minor']);
    }

    public function test_bad_rows_get_an_error_and_do_not_block_the_others(): void
    {
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-12.50,ok\nnot-a-date,-1.00,bad date\n2026-10-03,abc,bad amount\n2026-10-04,1.234,three decimals\n");

        $this->map($id)->assertOk()->assertJsonPath('summary.ready', 1)->assertJsonPath('summary.errors', 3);

        $this->assertSame('The date does not match the chosen format.', $this->row($id, 3)->error);
        $this->assertNull($this->row($id, 3)->normalized);
        $this->assertSame('The amount is not a valid number.', $this->row($id, 4)->error);
    }

    public function test_debit_and_credit_both_filled_is_an_error(): void
    {
        $id = $this->upload("d,a,b,x\n2026-10-01,5,6,both\n");

        $this->map($id, ['header_row' => 1, 'amount_mode' => 'split', 'debit_column' => 1, 'credit_column' => 2, 'description_column' => 3])
            ->assertOk();

        $this->assertSame('Both the debit and the credit column are filled.', $this->row($id, 2)->error);
    }

    public function test_category_names_are_matched_to_the_owners_own_categories_only(): void
    {
        $mine = TransactionCategory::create(['user_id' => $this->user->id, 'name' => 'Groceries', 'scope' => 'generic', 'is_active' => true]);
        TransactionCategory::create(['user_id' => $this->user->id, 'name' => 'Streaming', 'scope' => 'subscription', 'is_active' => true]);
        TransactionCategory::create(['user_id' => User::factory()->create()->id, 'name' => 'Secret', 'scope' => 'generic', 'is_active' => true]);
        $id = $this->upload("d,a,x,c\n2026-10-01,-5,shop,groceries\n2026-10-02,-6,shop,Secret\n2026-10-03,-7,shop,Streaming\n");

        $this->map($id, ['category_column' => 3])->assertOk();

        $this->assertSame($mine->id, $this->row($id, 2)->normalized['category_id']);
        $this->assertNull($this->row($id, 3)->normalized['category_id']);
        $this->assertNull($this->row($id, 4)->normalized['category_id'], 'subscription-scoped categories are not matched');
    }

    public function test_the_mapping_is_remembered_for_the_target_and_offered_again(): void
    {
        $first = $this->upload("Date,Amount,Description\n2026-10-01,-1,a\n");
        $this->map($first, ['decimal_separator' => '.'])->assertOk();

        $second = $this->upload("Date,Amount,Description\n2026-11-01,-2,b\n");
        $this->getJson(self::URL . "/{$second}")->assertOk()
            ->assertJsonPath('saved_mapping.date_format', 'Y-m-d')
            ->assertJsonPath('saved_mapping.amount_column', 1);
    }

    public function test_reapplying_a_mapping_leaves_discarded_and_confirmed_rows_alone(): void
    {
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-1.00,a\n2026-10-02,-2.00,b\n2026-10-03,-3.00,c\n");
        $this->map($id)->assertOk();
        $this->row($id, 3)->update(['status' => ImportRowStatus::DISCARDED]);
        $this->row($id, 4)->update(['status' => ImportRowStatus::CONFIRMED]);

        // Header row 0: line 1 becomes a data row (an error), amounts are now inverted.
        $this->map($id, ['header_row' => 0, 'invert_sign' => true])->assertOk()->assertJsonPath('data.mapping_version', 2);

        $this->assertSame(ImportRowStatus::DRAFT, $this->row($id, 1)->status);
        $this->assertNotNull($this->row($id, 1)->error);
        $this->assertSame(-200, $this->row($id, 3)->normalized['amount_minor'], 'the discarded row keeps its earlier normalisation');
        $this->assertSame(ImportRowStatus::CONFIRMED, $this->row($id, 4)->status);
        $this->assertSame(-300, $this->row($id, 4)->normalized['amount_minor']);
        $this->assertSame(100, $this->row($id, 2)->normalized['amount_minor']);
    }

    public function test_a_completed_batch_cannot_be_remapped_and_strangers_get_404(): void
    {
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-1,a\n");
        ImportBatch::whereKey($id)->update(['status' => 'completed']);
        $this->map($id)->assertStatus(409);

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->map($id)->assertNotFound();
    }

    public function test_invalid_mappings_are_rejected(): void
    {
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-1,a\n");

        $this->map($id, ['date_format' => 'D, d M Y'])->assertUnprocessable();
        $this->map($id, ['decimal_separator' => ',', 'thousands_separator' => ','])->assertUnprocessable();
        $this->map($id, ['amount_mode' => 'split', 'debit_column' => 1, 'credit_column' => 1])->assertUnprocessable();
        $this->map($id, ['amount_column' => 99])->assertUnprocessable();
        $this->putJson(self::URL . "/{$id}/mapping", [])->assertUnprocessable();
    }

    public function test_normalised_data_is_encrypted_and_the_fingerprint_is_a_keyed_hash(): void
    {
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-12.50,Secret Merchant\n");
        $this->map($id)->assertOk();

        $stored = DB::table('import_rows')->where('import_batch_id', $id)->where('row_number', 2)->first();
        $this->assertStringNotContainsString('Secret Merchant', (string) $stored->normalized);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $stored->fingerprint);
        $this->assertStringNotContainsString('Secret', $stored->fingerprint);
    }

    public function test_mapping_never_writes_to_the_ledger(): void
    {
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-12.50,Coffee\n");
        $this->map($id)->assertOk();

        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
        $this->assertSame(0, CreditCardExpense::count());
        $this->assertEquals(1000.0, $this->account->fresh()->balance);
    }

    // ─── duplicate detection ────────────────────────────────────────────────────────────────

    public function test_exact_and_probable_matches_against_the_ledger(): void
    {
        $this->ledgerTransaction(['amount' => -12.50, 'date' => '2026-10-01', 'description' => 'COFFEE SHOP MILANO']);
        $this->ledgerTransaction(['amount' => -40.00, 'date' => '2026-10-04', 'description' => 'Something else']);
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-12.50,Coffee Shop\n2026-10-02,-40.00,Groceries\n2026-10-03,-99.00,Brand new\n");

        $this->map($id)->assertOk()->assertJsonPath('summary.exact', 1)->assertJsonPath('summary.probable', 1);

        $this->assertSame('exact', $this->row($id, 2)->duplicate_class);
        $this->assertSame('probable', $this->row($id, 3)->duplicate_class);
        $this->assertSame('none', $this->row($id, 4)->duplicate_class);
        $this->assertSame('transaction', $this->row($id, 2)->flags['match']['type']);
        $this->assertNull($this->row($id, 4)->flags['match']);
    }

    public function test_a_date_outside_the_window_or_a_different_amount_is_not_a_match(): void
    {
        $this->ledgerTransaction(['amount' => -12.50, 'date' => '2026-10-10', 'description' => 'Coffee']);
        $this->ledgerTransaction(['amount' => -12.51, 'date' => '2026-10-01', 'description' => 'Coffee']);
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-12.50,Coffee\n");

        $this->map($id)->assertOk();

        $this->assertSame('none', $this->row($id, 2)->duplicate_class);
    }

    public function test_one_ledger_row_cannot_be_claimed_by_two_identical_import_rows(): void
    {
        $this->ledgerTransaction(['amount' => -5.00, 'date' => '2026-10-01', 'description' => 'Bus ticket']);
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-5.00,Bus ticket\n2026-10-01,-5.00,Bus ticket\n");

        $this->map($id)->assertOk();

        $this->assertSame('exact', $this->row($id, 2)->duplicate_class);
        $this->assertSame('none', $this->row($id, 3)->duplicate_class, 'the second legitimate charge stays importable');
        $this->assertContains('within_file_duplicate', $this->row($id, 3)->flags['codes']);
        $this->assertNotSame($this->row($id, 2)->fingerprint, $this->row($id, 3)->fingerprint);
    }

    public function test_the_same_file_imported_twice_is_flagged_as_already_imported(): void
    {
        $csv = "Date,Amount,Description\n2026-10-01,-12.50,Coffee\n2026-10-02,-3.00,Tea\n";
        $first = $this->upload($csv);
        $this->map($first)->assertOk()->assertJsonPath('summary.already_imported', 0);

        $second = $this->upload($csv);
        $this->map($second)->assertOk()->assertJsonPath('summary.already_imported', 2);
        $this->assertSame($first, $this->row($second, 2)->flags['already_imported_in']);
        $this->assertSame(2, ImportRow::where('import_batch_id', $second)->where('status', 'draft')->count(), 'flagged, never auto-dropped');

        // Deleting the first batch (or discarding its row) makes a re-import clean again.
        $this->row($first, 2)->update(['status' => ImportRowStatus::DISCARDED]);
        $this->map($second)->assertOk()->assertJsonPath('summary.already_imported', 1);
        $this->deleteJson(self::URL . "/{$first}")->assertNoContent();
        $this->map($second)->assertOk()->assertJsonPath('summary.already_imported', 0);
    }

    public function test_look_alikes_transfer_card_payment_renewal_and_currency_mismatch(): void
    {
        $savings = Account::factory()->create(['user_id' => $this->user->id, 'currency' => 'EUR']);
        $transfer = $this->ledgerTransaction(['account_id' => $savings->id, 'amount' => 200.00, 'date' => '2026-10-02', 'description' => 'in'], income: true);
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $payment = CreditCardPayment::create([
            'credit_card_id' => $card->id, 'due_date' => '2026-10-05',
            'installment_amount' => 148, 'interest_amount' => 0, 'principal_amount' => 148,
            'stamp_duty_amount' => 2, 'total_amount' => 150, 'status' => 'pending',
        ]);
        $renewal = $this->ledgerTransaction(['amount' => -9.99, 'date' => '2026-10-03', 'description' => 'Streaming']);
        DB::table('transactions')->where('id', $renewal->id)->update(['subscription_id' => DB::table('subscriptions')->insertGetId([
            'user_id' => $this->user->id, 'name' => 'Streaming', 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
        ])]);

        $id = $this->upload("Date,Amount,Description,Cur\n2026-10-02,-200.00,To savings,EUR\n2026-10-06,-150.00,Card settlement,EUR\n2026-10-03,-9.99,Streaming,EUR\n2026-10-04,-1.00,Foreign,USD\n");
        $this->map($id, ['currency_column' => 3])->assertOk();

        $this->assertContains('possible_transfer', $this->row($id, 2)->flags['codes']);
        $this->assertSame($transfer->id, $this->row($id, 2)->flags['related']['transaction_id']);
        $this->assertContains('possible_card_payment', $this->row($id, 3)->flags['codes']);
        $this->assertSame($payment->id, $this->row($id, 3)->flags['related']['card_payment_id']);
        $this->assertContains('matches_generated_renewal', $this->row($id, 4)->flags['codes']);
        $this->assertSame('exact', $this->row($id, 4)->duplicate_class);
        $this->assertContains('currency_mismatch', $this->row($id, 5)->flags['codes']);
    }

    public function test_card_batches_match_card_expenses_and_flag_credits(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        DB::table('credit_card_expenses')->insert([
            'credit_card_id' => $card->id, 'spent_at' => '2026-10-01', 'posted_at' => '2026-10-01',
            'amount' => 30.00, 'description' => 'Restaurant', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-30.00,Restaurant\n2026-10-02,15.00,Refund\n", ['credit_card_id' => $card->id]);

        $this->map($id)->assertOk();

        $this->assertSame('exact', $this->row($id, 2)->duplicate_class);
        $this->assertSame('credit_card_expense', $this->row($id, 2)->flags['match']['type']);
        $this->assertContains('card_credit', $this->row($id, 3)->flags['codes']);
        $this->assertSame('none', $this->row($id, 3)->duplicate_class);
    }

    public function test_other_users_ledger_rows_never_match(): void
    {
        $stranger = User::factory()->create();
        $theirs = Account::factory()->create(['user_id' => $stranger->id]);
        DB::table('transactions')->insert([
            'user_id' => $stranger->id, 'account_id' => $theirs->id, 'amount' => -12.50, 'date' => '2026-10-01',
            'transaction_type_id' => TransactionType::query()->firstOrCreate(['name' => 'Expense'], ['is_income' => false])->id,
            'description' => 'Coffee', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-12.50,Coffee\n");

        $this->map($id)->assertOk();

        $this->assertSame('none', $this->row($id, 2)->duplicate_class);
        $this->assertSame([], $this->row($id, 2)->flags['related']);
    }

    // ─── review fixes ───────────────────────────────────────────────────────────────────────

    private function xlsxUpload(array $rows): UploadedFile
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($rows as $r => $cells) {
            foreach ($cells as $c => $value) {
                $coordinate = chr(65 + $c) . ($r + 1);

                if (is_string($value)) {
                    $sheet->setCellValueExplicit($coordinate, $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                } else {
                    $sheet->setCellValue($coordinate, $value);
                }
            }
        }

        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'statement.xlsx', null, null, true);
    }

    public function test_xlsx_numbers_keep_their_value_under_a_european_text_mapping(): void
    {
        $id = $this->post(self::URL, [
            'account_id' => $this->account->id,
            'file' => $this->xlsxUpload([['Date', 'Amount', 'Memo'], ['2026-10-01', -12.5, 'Numeric cell'], ['2026-10-02', '1.234,56', 'Text cell']]),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        // A European mapping: the dot is a thousands separator for TEXT, never for a real number.
        $this->map($id, ['decimal_separator' => ',', 'thousands_separator' => '.'])->assertOk()->assertJsonPath('summary.ready', 2);

        $this->assertSame(-1250, $this->row($id, 2)->normalized['amount_minor'], 'a numeric -12.5 stays -12.50, not -125.00');
        $this->assertSame(123456, $this->row($id, 3)->normalized['amount_minor'], 'text still follows the configured separators');
    }

    public function test_the_upload_can_name_the_csv_delimiter(): void
    {
        $csv = "2026-10-01;-12,50;Coffee\n2026-10-02;-3,00;Tea\n";

        $this->post(self::URL, ['account_id' => $this->account->id, 'file' => UploadedFile::fake()->createWithContent('s.csv', $csv)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->post(self::URL, ['account_id' => $this->account->id, 'delimiter' => 'semicolon', 'file' => UploadedFile::fake()->createWithContent('s.csv', $csv)], ['Accept' => 'application/json'])
            ->assertCreated()->assertJsonPath('data.preview.0.cells.2', 'Coffee');

        $this->post(self::URL, ['account_id' => $this->account->id, 'delimiter' => 'bogus', 'file' => UploadedFile::fake()->createWithContent('s.csv', $csv)], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('delimiter');
    }

    public function test_editing_a_draft_reclassifies_it_without_changing_its_fingerprint(): void
    {
        $this->ledgerTransaction(['amount' => -77.00, 'date' => '2026-10-01', 'description' => 'Hardware store']);
        $id = $this->upload("Date,Amount,Description\n2026-10-01,-5.00,Hardware store\n");
        $this->map($id)->assertOk();
        $row = $this->row($id, 2);
        $fingerprint = $row->fingerprint;
        $this->assertSame('none', $row->duplicate_class);

        $this->patchJson(self::URL . "/{$id}/rows/{$row->id}", ['amount' => '-77.00'])->assertOk()->assertJsonPath('data.duplicate_class', 'exact');
        $this->assertSame($fingerprint, $this->row($id, 2)->fingerprint, 'the fingerprint identifies the statement line, not the edit');

        $this->patchJson(self::URL . "/{$id}/rows/{$row->id}", ['amount' => '-5.00'])->assertOk()->assertJsonPath('data.duplicate_class', 'none');
    }

    public function test_a_mapping_that_read_no_row_is_not_remembered_as_the_targets_default(): void
    {
        $first = $this->upload("Date,Amount,Description\n2026-10-01,-1.00,a\n");
        // Wrong columns: every line becomes an error.
        $this->map($first, ['amount_column' => 2, 'description_column' => 1])->assertOk()->assertJsonPath('summary.ready', 0);

        $second = $this->upload("Date,Amount,Description\n2026-11-01,-2.00,b\n");
        $this->getJson(self::URL . "/{$second}")->assertOk()->assertJsonPath('saved_mapping', null);

        // A mapping that works is remembered, and replaces nothing when a later bad one is tried.
        $this->map($second)->assertOk()->assertJsonPath('summary.ready', 1);
        $third = $this->upload("Date,Amount,Description\n2026-12-01,-3.00,c\n");
        $this->map($third, ['amount_column' => 2])->assertOk()->assertJsonPath('summary.ready', 0);

        $fourth = $this->upload("Date,Amount,Description\n2027-01-01,-4.00,d\n");
        $this->getJson(self::URL . "/{$fourth}")->assertOk()->assertJsonPath('saved_mapping.amount_column', 1);
    }
}
