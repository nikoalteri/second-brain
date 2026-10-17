<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ImportBatchStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ImportBatchApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/import-batches';

    private function csv(string $content = "Date,Amount,Description\n2026-10-01,-12.50,Coffee Shop\n2026-10-02,-40.00,Groceries\n", string $name = 'statement.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_it_requires_authentication(): void
    {
        $this->postJson(self::URL)->assertUnauthorized();
        $this->getJson(self::URL)->assertUnauthorized();
    }

    public function test_upload_to_an_account_stages_draft_rows_and_never_touches_the_ledger(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'balance' => 500, 'currency' => 'EUR']);
        Sanctum::actingAs($user);

        $response = $this->post(self::URL, [
            'account_id' => $account->id,
            'file' => $this->csv(),
            'statement_ending_balance' => '1234.56',
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
        ], ['Accept' => 'application/json'])->assertCreated();

        $response->assertJsonPath('data.currency', 'EUR')
            ->assertJsonPath('data.status', 'uploaded')
            ->assertJsonPath('data.file_format', 'csv')
            ->assertJsonPath('data.row_count', 3)
            ->assertJsonPath('data.statement_ending_balance', '1234.56')
            ->assertJsonPath('data.period_end', '2026-10-31')
            ->assertJsonPath('data.preview.1.cells.2', 'Coffee Shop')
            ->assertJsonPath('data.preview.1.status', 'draft');
        $this->assertArrayNotHasKey('user_id', $response->json('data'));

        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
        $this->assertSame(0, DB::table('credit_card_expenses')->count());
        $this->assertEquals(500.0, $account->fresh()->balance);
        $this->assertSame(3, ImportRow::count());
    }

    public function test_row_contents_are_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $this->post(self::URL, ['account_id' => $account->id, 'file' => $this->csv()], ['Accept' => 'application/json'])->assertCreated();

        $stored = DB::table('import_rows')->pluck('raw_cells')->implode('|');
        $this->assertStringNotContainsString('Coffee Shop', $stored);
        $this->assertStringNotContainsString('Groceries', $stored);
        $this->assertSame(['2026-10-01', '-12.50', 'Coffee Shop'], ImportRow::where('row_number', 2)->firstOrFail()->raw_cells);
    }

    public function test_upload_to_a_credit_card_uses_the_settlement_account_currency(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'currency' => 'USD']);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        Sanctum::actingAs($user);

        $this->post(self::URL, ['credit_card_id' => $card->id, 'file' => $this->csv()], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.credit_card_id', $card->id)
            ->assertJsonPath('data.account_id', null)
            ->assertJsonPath('data.currency', 'USD');
    }

    public function test_exactly_one_target_is_required(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        Sanctum::actingAs($user);

        $this->post(self::URL, ['file' => $this->csv()], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post(self::URL, ['account_id' => $account->id, 'credit_card_id' => $card->id, 'file' => $this->csv()], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_foreign_inactive_and_currencyless_targets_are_rejected(): void
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $foreign = Account::factory()->create(['user_id' => $stranger->id]);
        $foreignCard = CreditCard::factory()->create(['user_id' => $stranger->id, 'account_id' => $foreign->id]);
        $inactive = Account::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        $noCurrency = Account::factory()->create(['user_id' => $user->id, 'currency' => '']);
        Sanctum::actingAs($user);

        foreach ([['account_id' => $foreign->id], ['credit_card_id' => $foreignCard->id], ['account_id' => $inactive->id], ['account_id' => $noCurrency->id]] as $target) {
            $this->post(self::URL, $target + ['file' => $this->csv()], ['Accept' => 'application/json'])->assertUnprocessable();
        }

        $this->assertSame(0, ImportBatch::count());
    }

    public function test_a_superadmin_cannot_import_into_another_users_account(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $foreign = Account::factory()->create();
        Sanctum::actingAs($admin);

        $this->post(self::URL, ['account_id' => $foreign->id, 'file' => $this->csv()], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_bad_files_are_rejected_without_staging_anything(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        foreach ([
            UploadedFile::fake()->createWithContent('statement.pdf', '%PDF-1.4'),
            UploadedFile::fake()->createWithContent('statement.csv', ''),
            UploadedFile::fake()->createWithContent('statement.csv', "A,B\n\0\0,1\n"),
            UploadedFile::fake()->createWithContent('statement.xlsx', 'not a workbook'),
        ] as $file) {
            $this->post(self::URL, ['account_id' => $account->id, 'file' => $file], ['Accept' => 'application/json'])->assertUnprocessable();
        }

        $this->assertSame(0, ImportBatch::count());
        $this->assertSame(0, ImportRow::count());
    }

    public function test_show_returns_the_preview_limited_to_the_configured_rows(): void
    {
        config(['import.preview_rows' => 2]);
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $id = $this->post(self::URL, ['account_id' => $account->id, 'file' => $this->csv("h\n1\n2\n3\n")], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.id');

        $this->getJson(self::URL . "/{$id}")->assertOk()
            ->assertJsonCount(2, 'data.preview')
            ->assertJsonPath('data.row_count', 4);
    }

    public function test_batches_are_private_even_for_a_superadmin(): void
    {
        Role::findOrCreate('superadmin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('superadmin');
        $stranger = User::factory()->create();
        $owner = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $owner->id]);
        Sanctum::actingAs($owner);
        $id = $this->post(self::URL, ['account_id' => $account->id, 'file' => $this->csv()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        foreach ([$stranger, $admin] as $other) {
            Sanctum::actingAs($other);
            $this->getJson(self::URL . "/{$id}")->assertNotFound();
            $this->deleteJson(self::URL . "/{$id}")->assertNotFound();
            $this->getJson(self::URL)->assertOk()->assertJsonCount(0, 'data');
        }

        $this->assertSame(1, ImportBatch::count());
    }

    public function test_owner_can_delete_a_draft_batch_with_its_rows_but_not_a_completed_one(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);
        $id = $this->post(self::URL, ['account_id' => $account->id, 'file' => $this->csv()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $this->deleteJson(self::URL . "/{$id}")->assertNoContent();
        $this->assertSame(0, ImportRow::count());

        $done = ImportBatch::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'currency' => 'EUR',
            'status' => ImportBatchStatus::COMPLETED, 'file_format' => 'csv', 'row_count' => 0,
        ]);
        $this->deleteJson(self::URL . "/{$done->id}")->assertStatus(409);
        $this->assertModelExists($done);
    }

    public function test_purge_removes_only_old_unconfirmed_batches(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $make = fn (ImportBatchStatus $status, string $age) => tap(ImportBatch::create([
            'user_id' => $user->id, 'account_id' => $account->id, 'currency' => 'EUR',
            'status' => $status, 'file_format' => 'csv', 'row_count' => 1,
        ]), function (ImportBatch $batch) use ($age) {
            $batch->rows()->create(['row_number' => 1, 'raw_cells' => ['x']]);
            DB::table('import_batches')->where('id', $batch->id)->update(['created_at' => Carbon::parse($age)]);
        });

        $oldDraft = $make(ImportBatchStatus::UPLOADED, '-40 days');
        $oldMapped = $make(ImportBatchStatus::MAPPED, '-31 days');
        $recent = $make(ImportBatchStatus::UPLOADED, '-5 days');
        $oldCompleted = $make(ImportBatchStatus::COMPLETED, '-90 days');

        $this->artisan('imports:purge-abandoned')->assertSuccessful();

        $this->assertModelMissing($oldDraft);
        $this->assertModelMissing($oldMapped);
        $this->assertModelExists($recent);
        $this->assertModelExists($oldCompleted);
        $this->assertSame(2, ImportRow::count());
    }
}
