<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanceReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_finance_report_summary_returns_user_scoped_report_data(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $account = Account::factory()->create(['user_id' => $user->id]);
        $otherAccount = Account::factory()->create(['user_id' => $otherUser->id]);

        $expenseType = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['is_income' => false],
        );

        $housing = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => 'Housing',
            'is_active' => true,
        ]);

        $rent = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'parent_id' => $housing->id,
            'name' => 'Rent',
            'is_active' => true,
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $rent->id,
            'amount' => -650.00,
            'date' => '2026-03-15',
            'notes' => 'March rent',
        ]);

        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'transaction_type_id' => $expenseType->id,
            'amount' => -100.00,
            'date' => '2026-03-10',
            'notes' => 'Other user expense',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/finance?year=2026');

        $response->assertOk()
            ->assertJsonPath('selected_year', 2026)
            ->assertJsonPath('pivot.tree.0.label', 'Housing')
            ->assertJsonPath('pivot.tree.0.children.0.label', 'Rent')
            ->assertJsonPath('pivot.pivot.Housing|Rent.3', -650)
            ->assertJsonPath('pivot.grandTotal', -650)
            ->assertJsonPath('pie.0.label', 'Housing');

        $noteOptions = $response->json('note_options');
        $this->assertArrayHasKey('March rent', $noteOptions);
        $this->assertArrayNotHasKey('Other user expense', $noteOptions);
    }

    public function test_finance_report_details_returns_matching_transactions(): void
    {
        $user = User::factory()->create();
        $categoryOwner = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id, 'name' => 'Main account']);

        $expenseType = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['is_income' => false],
        );

        $housing = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $categoryOwner->id,
            'name' => 'Housing',
            'is_active' => true,
        ]);

        $rent = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $categoryOwner->id,
            'parent_id' => $housing->id,
            'name' => 'Rent',
            'is_active' => true,
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $rent->id,
            'amount' => -650.00,
            'date' => '2026-03-15',
            'description' => 'March rent',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/reports/finance/details?year=2026&month=3&category_key=Housing%7CRent');

        $response->assertOk()
            ->assertJsonPath('transactions.0.description', 'March rent')
            ->assertJsonPath('transactions.0.account_name', 'Main account');

        $this->assertEquals(-650.0, $response->json('transactions.0.amount'));
        $this->assertEquals(-650.0, $response->json('total'));
    }
}
