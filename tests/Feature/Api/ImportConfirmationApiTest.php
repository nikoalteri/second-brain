<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\ImportBatchStatus;
use App\Enums\ImportRowStatus;
use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use App\Services\Import\ImportRowPromotionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ImportConfirmationApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/v1/import-batches';

    private User $user;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-10-17 10:00:00');
        $this->user = User::factory()->create();
        $this->account = Account::factory()->create(['user_id' => $this->user->id, 'currency' => 'EUR', 'balance' => 1000]);
        Sanctum::actingAs($this->user);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function mapped(string $csv, array $target = []): int
    {
        $id = $this->post(self::URL, ($target ?: ['account_id' => $this->account->id]) + [
            'file' => UploadedFile::fake()->createWithContent('s.csv', $csv),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');

        $this->putJson(self::URL . "/{$id}/mapping", [
            'header_row' => 1, 'date_column' => 0, 'date_format' => 'Y-m-d', 'amount_mode' => 'single', 'amount_column' => 1,
            'decimal_separator' => '.', 'thousands_separator' => ',', 'description_column' => 2,
        ])->assertOk();

        return $id;
    }

    private function row(int $batch, int $number): ImportRow
    {
        return ImportRow::where('import_batch_id', $batch)->where('row_number', $number)->firstOrFail();
    }

    private function confirm(int $id, array $body)
    {
        return $this->postJson(self::URL . "/{$id}/confirm", $body);
    }

    // ─── review ─────────────────────────────────────────────────────────────────────────────

    public function test_rows_can_be_listed_filtered_and_paginated(): void
    {
        $id = $this->mapped("Date,Amount,Description\n2026-10-01,-1.00,a\nbad,-2.00,b\n2026-10-03,-3.00,c\n");

        $this->getJson(self::URL . "/{$id}/rows")->assertOk()->assertJsonCount(4, 'data');
        $this->getJson(self::URL . "/{$id}/rows?errors=1")->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.error', 'The date does not match the chosen format.');
        $this->getJson(self::URL . "/{$id}/rows?status=skipped")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson(self::URL . "/{$id}/rows?status=bogus")->assertUnprocessable();
    }

    public function test_a_row_can_be_edited_discarded_and_restored(): void
    {
        $cat = TransactionCategory::create(['user_id' => $this->user->id, 'name' => 'Food', 'scope' => 'generic', 'is_active' => true]);
        $id = $this->mapped("Date,Amount,Description\n2026-10-01,-1.00,a\n");
        $row = $this->row($id, 2);

        $this->patchJson(self::URL . "/{$id}/rows/{$row->id}", ['description' => 'Lunch', 'amount' => '-9.90', 'category_id' => $cat->id, 'date' => '2026-10-02'])
            ->assertOk()
            ->assertJsonPath('data.normalized.description', 'Lunch')
            ->assertJsonPath('data.normalized.amount_minor', -990)
            ->assertJsonPath('data.normalized.category_id', $cat->id)
            ->assertJsonPath('data.normalized.date', '2026-10-02');

        $this->patchJson(self::URL . "/{$id}/rows/{$row->id}", ['status' => 'discarded'])->assertOk()->assertJsonPath('data.status', 'discarded');
        $this->patchJson(self::URL . "/{$id}/rows/{$row->id}", ['description' => 'x'])->assertStatus(409);
        $this->patchJson(self::URL . "/{$id}/rows/{$row->id}", ['status' => 'draft'])->assertOk()->assertJsonPath('data.status', 'draft');
    }

    public function test_row_edit_validation_and_ownership(): void
    {
        $stranger = User::factory()->create();
        $theirs = TransactionCategory::create(['user_id' => $stranger->id, 'name' => 'Theirs', 'scope' => 'generic', 'is_active' => true]);
        $sub = TransactionCategory::create(['user_id' => $this->user->id, 'name' => 'Subs', 'scope' => 'subscription', 'is_active' => true]);
        $id = $this->mapped("Date,Amount,Description\n2026-10-01,-1.00,a\nbad,-2.00,b\n");
        $row = $this->row($id, 2);
        $url = self::URL . "/{$id}/rows/{$row->id}";

        $this->patchJson($url, ['category_id' => $theirs->id])->assertUnprocessable();
        $this->patchJson($url, ['category_id' => $sub->id])->assertUnprocessable();
        $this->patchJson($url, ['amount' => '0'])->assertUnprocessable();
        $this->patchJson($url, ['amount' => '1.234'])->assertUnprocessable();
        $this->patchJson($url, ['date' => '01/10/2026'])->assertUnprocessable();
        $this->patchJson(self::URL . "/{$id}/rows/{$this->row($id, 3)->id}", ['description' => 'x'])->assertUnprocessable(); // error row

        Sanctum::actingAs($stranger);
        $this->patchJson($url, ['description' => 'x'])->assertNotFound();
        $this->getJson(self::URL . "/{$id}/rows")->assertNotFound();
    }

    // ─── confirmation: account ──────────────────────────────────────────────────────────────

    public function test_confirming_posts_a_transaction_once_and_moves_the_balance_once(): void
    {
        $cat = TransactionCategory::create(['user_id' => $this->user->id, 'name' => 'Food', 'scope' => 'generic', 'is_active' => true]);
        $id = $this->mapped("Date,Amount,Description\n2026-10-01,-12.50,Coffee\n2026-10-02,200.00,Refund\n");
        $row = $this->row($id, 2);
        $this->patchJson(self::URL . "/{$id}/rows/{$row->id}", ['category_id' => $cat->id])->assertOk();
        $ids = ImportRow::where('import_batch_id', $id)->where('status', 'draft')->pluck('id')->all();

        $this->confirm($id, ['row_ids' => $ids])->assertOk()
            ->assertJsonPath('data.confirmed', 2)->assertJsonPath('data.status', 'completed');

        $expense = Transaction::findOrFail($this->row($id, 2)->transaction_id);
        $this->assertEquals(-12.5, $expense->amount);
        $this->assertSame($cat->id, $expense->transaction_category_id);
        $this->assertSame('Coffee', $expense->description);
        $this->assertSame('2026-10-01', $expense->date->toDateString());
        $this->assertEquals(200.0, Transaction::findOrFail($this->row($id, 3)->transaction_id)->amount);
        $this->assertEquals(1000 - 12.5 + 200, $this->account->fresh()->balance);

        // The same rows again: nothing is posted twice.
        $this->confirm($id, ['row_ids' => $ids])->assertStatus(409);
        $this->assertSame(2, Transaction::withoutGlobalScopes()->count());
        $this->assertEquals(1187.5, $this->account->fresh()->balance);
    }

    public function test_a_repeated_promotion_of_the_same_row_is_a_no_op(): void
    {
        $id = $this->mapped("Date,Amount,Description\n2026-10-01,-5.00,Bus\n2026-10-02,-6.00,Tram\n");
        $batch = ImportBatch::findOrFail($id);
        $rowId = $this->row($id, 2)->id;
        $service = app(ImportRowPromotionService::class);

        $first = $service->promote($batch, $rowId);
        $second = $service->promote($batch, $rowId);

        $this->assertSame('confirmed', $first['outcome']);
        $this->assertSame('skipped', $second['outcome']);
        $this->assertSame(1, Transaction::withoutGlobalScopes()->count());
        $this->assertEquals(995.0, $this->account->fresh()->balance);
    }

    public function test_the_unique_promotion_index_blocks_two_rows_pointing_at_one_record(): void
    {
        $id = $this->mapped("Date,Amount,Description\n2026-10-01,-5.00,Bus\n2026-10-02,-6.00,Tram\n");
        $this->confirm($id, ['row_ids' => [$this->row($id, 2)->id]])->assertOk();
        $posted = $this->row($id, 2)->transaction_id;

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->row($id, 3)->forceFill(['transaction_id' => $posted])->save();
    }

    public function test_all_ready_skips_flagged_rows_but_an_explicit_choice_can_include_them(): void
    {
        $type = TransactionType::query()->firstOrCreate(['name' => 'Expense'], ['is_income' => false]);
        Transaction::create(['user_id' => $this->user->id, 'account_id' => $this->account->id, 'transaction_type_id' => $type->id, 'amount' => -12.50, 'date' => '2026-10-01', 'description' => 'Coffee']);
        $csv = "Date,Amount,Description\n2026-10-01,-12.50,Coffee\n2026-10-05,-30.00,New one\n2026-10-05,-30.00,New one\n";
        $first = $this->mapped($csv);

        $this->confirm($first, ['all_ready' => true])->assertOk()
            ->assertJsonPath('data.confirmed', 2)
            ->assertJsonPath('data.status', 'mapped');

        $duplicate = $this->row($first, 2);
        $this->assertSame(ImportRowStatus::DRAFT, $duplicate->status, 'the exact ledger match is left for the owner');

        // The identical legitimate second charge was posted too (it is not a duplicate of the ledger).
        $this->assertSame(1, Transaction::query()->where('description', 'New one')->count() - 1);

        $this->confirm($first, ['row_ids' => [$duplicate->id]])->assertOk()->assertJsonPath('data.confirmed', 1)->assertJsonPath('data.status', 'completed');
    }

    public function test_the_same_file_imported_again_is_not_posted_by_all_ready(): void
    {
        $csv = "Date,Amount,Description\n2026-10-01,-12.50,Coffee\n";
        $first = $this->mapped($csv);
        $this->confirm($first, ['all_ready' => true])->assertOk()->assertJsonPath('data.confirmed', 1);

        $second = $this->mapped($csv);
        $this->confirm($second, ['all_ready' => true])->assertOk()->assertJsonPath('data.confirmed', 0);

        $this->assertSame(1, Transaction::withoutGlobalScopes()->count());
    }

    public function test_confirmation_requires_a_mapped_batch_and_a_valid_body(): void
    {
        $id = $this->post(self::URL, ['account_id' => $this->account->id, 'file' => UploadedFile::fake()->createWithContent('s.csv', "a,b,c\n1,2,3\n")], ['Accept' => 'application/json'])->json('data.id');

        $this->confirm($id, ['all_ready' => true])->assertStatus(409);
        $this->confirm($id, [])->assertUnprocessable();
        $this->confirm($id, ['row_ids' => []])->assertUnprocessable();
    }

    public function test_rows_with_errors_discarded_or_foreign_rows_are_skipped_not_posted(): void
    {
        $id = $this->mapped("Date,Amount,Description\n2026-10-01,-1.00,a\nbad,-2.00,b\n2026-10-03,-3.00,c\n");
        $this->row($id, 4)->update(['status' => ImportRowStatus::DISCARDED]);

        $other = $this->mapped("Date,Amount,Description\n2026-10-09,-9.00,other\n");
        $foreign = $this->row($other, 2)->id;

        $response = $this->confirm($id, ['row_ids' => [$this->row($id, 2)->id, $this->row($id, 3)->id, $this->row($id, 4)->id, $foreign]])->assertOk();

        $this->assertSame(['confirmed', 'skipped', 'skipped', 'skipped'], array_column($response->json('data.outcomes'), 'outcome'));
        $this->assertSame(1, Transaction::withoutGlobalScopes()->count());
        $this->assertSame(ImportRowStatus::DRAFT, $this->row($other, 2)->status);
    }

    public function test_a_target_that_became_inactive_fails_safely_without_posting(): void
    {
        $id = $this->mapped("Date,Amount,Description\n2026-10-01,-1.00,a\n");
        $this->account->update(['is_active' => false]);

        $this->confirm($id, ['row_ids' => [$this->row($id, 2)->id]])->assertOk()->assertJsonPath('data.skipped', 1);

        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
        $this->assertSame(ImportRowStatus::DRAFT, $this->row($id, 2)->status);
    }

    public function test_other_users_cannot_confirm_a_batch(): void
    {
        $id = $this->mapped("Date,Amount,Description\n2026-10-01,-1.00,a\n");
        Sanctum::actingAs(User::factory()->create());

        $this->confirm($id, ['all_ready' => true])->assertNotFound();
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
    }

    // ─── confirmation: credit cards ─────────────────────────────────────────────────────────

    public function test_card_charges_become_expenses_and_credits_are_never_auto_posted(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $id = $this->mapped("Date,Amount,Description\n2026-10-05,-30.00,Restaurant\n2026-10-06,15.00,Refund\n", ['credit_card_id' => $card->id]);

        $this->confirm($id, ['all_ready' => true])->assertOk()->assertJsonPath('data.confirmed', 1);
        $this->assertSame(ImportRowStatus::DRAFT, $this->row($id, 3)->status, 'card credits wait for the owner');

        $expense = CreditCardExpense::findOrFail($this->row($id, 2)->credit_card_expense_id);
        $this->assertEquals(30.0, $expense->amount);
        $this->assertSame($card->id, $expense->credit_card_id);
        $this->assertNotNull($expense->credit_card_cycle_id, 'observers assigned a cycle');
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count(), 'a card charge is not a bank debit');
        $this->assertEquals(1000.0, $this->account->fresh()->balance);

        $this->confirm($id, ['row_ids' => [$this->row($id, 3)->id]])->assertOk()
            ->assertJsonPath('data.skipped', 1)
            ->assertJsonPath('data.outcomes.0.reason', 'Card credits and payments are not imported automatically; discard this row.');
        $this->assertSame(1, CreditCardExpense::count());
    }

    public function test_a_card_expense_in_an_issued_cycle_fails_with_a_reason_and_stays_retryable(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $id = $this->mapped("Date,Amount,Description\n2026-10-05,-30.00,Restaurant\n2026-10-06,-5.00,Cafe\n", ['credit_card_id' => $card->id]);

        $this->confirm($id, ['row_ids' => [$this->row($id, 2)->id]])->assertOk()->assertJsonPath('data.confirmed', 1);
        CreditCardCycle::query()->where('credit_card_id', $card->id)->update(['status' => 'issued']);

        $response = $this->confirm($id, ['row_ids' => [$this->row($id, 3)->id]])->assertOk();

        $response->assertJsonPath('data.failed', 1);
        $this->assertStringContainsString('billing cycle', (string) $response->json('data.outcomes.0.reason'));
        $this->assertSame(ImportRowStatus::DRAFT, $this->row($id, 3)->status);
        $this->assertNotNull($this->row($id, 3)->failure_reason);
        $this->assertSame(1, CreditCardExpense::count());
    }

    public function test_one_failing_row_does_not_block_the_others_in_a_batch(): void
    {
        $card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $this->account->id]);
        $id = $this->mapped("Date,Amount,Description\n2026-10-05,-30.00,A\n2026-10-06,-5.00,B\n", ['credit_card_id' => $card->id]);
        // Make the first row's amount unpostable by emptying its data after mapping.
        $this->row($id, 2)->forceFill(['normalized' => null, 'error' => 'broken'])->save();

        $this->confirm($id, ['all_ready' => true])->assertOk()->assertJsonPath('data.confirmed', 1);
        $this->assertSame(1, CreditCardExpense::count());
    }

    public function test_completion_requires_no_draft_rows_left(): void
    {
        $id = $this->mapped("Date,Amount,Description\n2026-10-01,-1.00,a\nbad,-2.00,b\n");

        $this->confirm($id, ['all_ready' => true])->assertOk()->assertJsonPath('data.status', 'mapped');

        $this->patchJson(self::URL . "/{$id}/rows/{$this->row($id, 3)->id}", ['status' => 'discarded'])->assertOk();
        $this->confirm($id, ['row_ids' => [$this->row($id, 3)->id]])->assertOk()->assertJsonPath('data.status', 'completed');
        $this->assertSame(ImportBatchStatus::COMPLETED, ImportBatch::findOrFail($id)->status);

        // A closed batch accepts no more edits or re-mapping.
        $this->patchJson(self::URL . "/{$id}/rows/{$this->row($id, 2)->id}", ['status' => 'discarded'])->assertStatus(409);
        $this->putJson(self::URL . "/{$id}/mapping", [
            'header_row' => 1, 'date_column' => 0, 'date_format' => 'Y-m-d', 'amount_mode' => 'single', 'amount_column' => 1,
            'decimal_separator' => '.', 'thousands_separator' => ',', 'description_column' => 2,
        ])->assertStatus(409);
    }
}
