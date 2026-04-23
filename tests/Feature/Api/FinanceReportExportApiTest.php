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

class FinanceReportExportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_returns_a_single_stacked_file_without_budget_sections(): void
    {
        [$user, $expenseType] = $this->seedExportFixtures();

        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/reports/finance/export?year=2026&format=csv&types[]=' . $expenseType->id . '&note=March%20rent');

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $response->assertHeader('content-disposition', 'attachment; filename=finance-report-2026.csv');

        $content = $response->streamedContent();

        $this->assertStringContainsString('Cashflow Summary', $content);
        $this->assertStringContainsString('Category Pivot', $content);
        $this->assertStringContainsString('Distribution', $content);
        $this->assertStringContainsString('Housing', $content);
        $this->assertStringNotContainsString('Groceries', $content);
        $this->assertStringNotContainsString('Budget', $content);
        $this->assertStringNotContainsString('alert_status', $content);
        $this->assertTrue(
            strpos($content, 'Cashflow Summary') < strpos($content, 'Category Pivot')
            && strpos($content, 'Category Pivot') < strpos($content, 'Distribution')
        );
    }

    public function test_xlsx_export_returns_a_workbook_download(): void
    {
        [$user] = $this->seedExportFixtures();

        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/reports/finance/export?year=2026&format=xlsx');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );
        $response->assertHeader('content-disposition', 'attachment; filename=finance-report-2026.xlsx');
    }

    public function test_pdf_export_returns_a_pdf_download(): void
    {
        [$user] = $this->seedExportFixtures();

        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/reports/finance/export?year=2026&format=pdf');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'attachment; filename=finance-report-2026.pdf');
    }

    public function test_export_uses_current_report_filters_for_finance_data_only(): void
    {
        [$user, $expenseType, $incomeType] = $this->seedExportFixtures();

        Sanctum::actingAs($user);

        $response = $this->get('/api/v1/reports/finance/export?year=2026&format=csv&types[]=' . $expenseType->id . '&note=March%20rent');

        $response->assertOk();

        $content = $response->streamedContent();

        $this->assertStringContainsString('Housing', $content);
        $this->assertStringNotContainsString('Salary', $content);
        $this->assertStringNotContainsString((string) $incomeType->id, $content);
        $this->assertStringNotContainsString('Budget Alerts', $content);
    }

    /**
     * @return array{User, TransactionType, TransactionType}
     */
    private function seedExportFixtures(): array
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $account = Account::factory()->create(['user_id' => $user->id]);
        $otherAccount = Account::factory()->create(['user_id' => $otherUser->id]);

        $expenseType = TransactionType::query()->firstOrCreate(
            ['name' => 'Expenses'],
            ['is_income' => false],
        );

        $incomeType = TransactionType::query()->firstOrCreate(
            ['name' => 'Earnings'],
            ['is_income' => true],
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

        $food = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => 'Food',
            'is_active' => true,
        ]);

        $groceries = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'parent_id' => $food->id,
            'name' => 'Groceries',
            'is_active' => true,
        ]);

        $salary = TransactionCategory::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => 'Salary',
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
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $groceries->id,
            'amount' => -120.00,
            'date' => '2026-03-18',
            'notes' => 'Groceries',
        ]);

        Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $incomeType->id,
            'transaction_category_id' => $salary->id,
            'amount' => 2500.00,
            'date' => '2026-03-01',
            'notes' => 'Salary',
        ]);

        Transaction::factory()->create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'transaction_type_id' => $expenseType->id,
            'amount' => -999.00,
            'date' => '2026-03-05',
            'notes' => 'Other user expense',
        ]);

        return [$user, $expenseType, $incomeType];
    }
}
