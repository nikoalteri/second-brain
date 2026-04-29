<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\Account;
use App\Models\CategoryBudget;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinanceReportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_finance_report_renders_budget_month_context_alerts_and_export_labels(): void
    {
        $user = $this->createAdminUser();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $expenseType = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['is_income' => false],
        );

        $warningCategory = $this->createLeafCategory($user->id, 'Housing', 'Rent');
        $exceededCategory = $this->createLeafCategory($user->id, 'Transport', 'Fuel');
        $criticalCategory = $this->createLeafCategory($user->id, 'Food', 'Groceries');

        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $warningCategory->id,
            'period_start' => '2026-04-01',
            'amount' => 100,
        ]);

        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $exceededCategory->id,
            'period_start' => '2026-04-01',
            'amount' => 100,
        ]);

        CategoryBudget::factory()->create([
            'user_id' => $user->id,
            'transaction_category_id' => $criticalCategory->id,
            'period_start' => '2026-04-01',
            'amount' => 100,
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $warningCategory->id,
            'amount' => -80,
            'date' => '2026-04-03',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $exceededCategory->id,
            'amount' => -100,
            'date' => '2026-04-05',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $criticalCategory->id,
            'amount' => -120,
            'date' => '2026-04-07',
        ]);

        $response = $this->actingAs($user)->get('/admin/finance-report');

        $response->assertOk()
            ->assertSee('Budget Month')
            ->assertSee('Budget Status')
            ->assertSee('warning')
            ->assertSee('exceeded')
            ->assertSee('critical')
            ->assertSee('CSV')
            ->assertSee('XLSX')
            ->assertSee('PDF');
    }

    public function test_admin_dashboard_renders_without_widgets(): void
    {
        $user = $this->createAdminUser();

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk()
            ->assertDontSee('Budget Alerts');
    }

    private function createAdminUser(): User
    {
        Role::create(['name' => 'superadmin']);

        $user = User::factory()->create([
            'is_active' => true,
        ]);
        $user->assignRole('superadmin');

        return $user;
    }

    private function createLeafCategory(int $userId, string $parentName, string $childName): TransactionCategory
    {
        $parent = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $userId,
            'name' => $parentName,
            'is_active' => true,
        ]);

        return TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $userId,
            'parent_id' => $parent->id,
            'name' => $childName,
            'is_active' => true,
        ]);
    }
}
