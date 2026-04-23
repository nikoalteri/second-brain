<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BudgetApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_monthly_budget_overview_returns_only_leaf_categories_for_the_authenticated_user(): void
    {
        Carbon::setTestNow('2026-04-23 12:00:00');

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
            'amount' => -150.00,
            'date' => '2026-04-12',
            'notes' => 'April rent',
        ]);

        $otherParent = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $otherUser->id,
            'name' => 'Travel',
            'is_active' => true,
        ]);

        $otherLeaf = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $otherUser->id,
            'parent_id' => $otherParent->id,
            'name' => 'Flights',
            'is_active' => true,
        ]);

        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $otherLeaf->id,
            'amount' => -400.00,
            'date' => '2026-04-10',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/budgets/monthly?year=2026&month=4');

        $response->assertOk()
            ->assertJsonPath('selected_year', 2026)
            ->assertJsonPath('selected_month', 4)
            ->assertJsonPath('period_start', '2026-04-01')
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.transaction_category_id', $rent->id)
            ->assertJsonPath('categories.0.parent_name', 'Housing')
            ->assertJsonPath('categories.0.name', 'Rent')
            ->assertJsonPath('categories.0.is_leaf', true)
            ->assertJsonPath('categories.0.budget_amount', null)
            ->assertJsonPath('categories.0.spent_amount', 150.0)
            ->assertJsonPath('categories.0.usage_ratio', null)
            ->assertJsonPath('categories.0.alert_status', 'none');
    }

    public function test_upserting_a_monthly_budget_rejects_parent_categories_with_422(): void
    {
        Carbon::setTestNow('2026-04-23 12:00:00');

        $user = User::factory()->create();

        $housing = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => 'Housing',
            'is_active' => true,
        ]);

        TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'parent_id' => $housing->id,
            'name' => 'Rent',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/budgets/monthly/{$housing->id}", [
            'year' => 2026,
            'month' => 4,
            'amount' => 500,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['transaction_category_id']);
    }

    public function test_upserting_and_clearing_a_monthly_budget_only_affects_the_selected_month(): void
    {
        Carbon::setTestNow('2026-04-23 12:00:00');

        $user = User::factory()->create();

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

        Sanctum::actingAs($user);

        $createApril = $this->putJson("/api/v1/budgets/monthly/{$rent->id}", [
            'year' => 2026,
            'month' => 4,
            'amount' => 500,
        ]);

        $createApril->assertOk();
        $this->assertDatabaseHas('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $rent->id,
            'period_start' => '2026-04-01 00:00:00',
            'amount' => '500.00',
        ]);

        $createMay = $this->putJson("/api/v1/budgets/monthly/{$rent->id}", [
            'year' => 2026,
            'month' => 5,
            'amount' => 450,
        ]);

        $createMay->assertOk();

        $deleteApril = $this->deleteJson("/api/v1/budgets/monthly/{$rent->id}?year=2026&month=4");

        $deleteApril->assertNoContent();

        $this->assertDatabaseMissing('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $rent->id,
            'period_start' => '2026-04-01 00:00:00',
        ]);

        $this->assertDatabaseHas('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $rent->id,
            'period_start' => '2026-05-01 00:00:00',
            'amount' => '450.00',
        ]);
    }

    public function test_budget_overview_and_upsert_allow_categories_used_by_the_users_transactions(): void
    {
        Carbon::setTestNow('2026-04-23 12:00:00');

        $user = User::factory()->create();
        $categoryOwner = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

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
            'amount' => -150.00,
            'date' => '2026-04-12',
        ]);

        Sanctum::actingAs($user);

        $overview = $this->getJson('/api/v1/budgets/monthly?year=2026&month=4');

        $overview->assertOk()
            ->assertJsonCount(1, 'categories')
            ->assertJsonPath('categories.0.transaction_category_id', $rent->id)
            ->assertJsonPath('categories.0.parent_name', 'Housing')
            ->assertJsonPath('categories.0.name', 'Rent');

        $upsert = $this->putJson("/api/v1/budgets/monthly/{$rent->id}", [
            'year' => 2026,
            'month' => 4,
            'amount' => 500,
        ]);

        $upsert->assertOk();

        $this->assertDatabaseHas('category_budgets', [
            'user_id' => $user->id,
            'transaction_category_id' => $rent->id,
            'period_start' => '2026-04-01 00:00:00',
            'amount' => '500.00',
        ]);
    }
}
