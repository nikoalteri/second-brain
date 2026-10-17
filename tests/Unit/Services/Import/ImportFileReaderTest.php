<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Import;

use App\Services\Import\ImportFileException;
use App\Services\Import\ImportFileReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportFileReaderTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function file(string $content, string $suffix = '.csv'): string
    {
        $path = tempnam(sys_get_temp_dir(), 'imp') . $suffix;
        file_put_contents($path, $content);

        return $this->files[] = $path;
    }

    private function xlsx(callable $fill): string
    {
        $spreadsheet = new Spreadsheet;
        $fill($spreadsheet->getActiveSheet());
        $path = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return $this->files[] = $path;
    }

    private function read(string $path, string $ext = 'csv'): array
    {
        return app(ImportFileReader::class)->read($path, $ext);
    }

    public function test_reads_a_comma_csv_with_quoted_multiline_fields(): void
    {
        $result = $this->read($this->file("Date,Amount,Description\n2026-10-01,\"-12,50\",\"Shop\nBranch 2\"\n"));

        $this->assertSame('csv', $result['format']);
        $this->assertSame(['2026-10-01', '-12,50', "Shop\nBranch 2"], $result['rows'][1]);
    }

    public function test_detects_a_semicolon_delimiter_and_strips_a_bom(): void
    {
        $result = $this->read($this->file("\xEF\xBB\xBFData;Importo;Descrizione\n01/10/2026;-1.234,56;Affitto\n"));

        $this->assertSame(['Data', 'Importo', 'Descrizione'], $result['rows'][0]);
        $this->assertSame(['01/10/2026', '-1.234,56', 'Affitto'], $result['rows'][1]);
    }

    public function test_converts_windows_1252_text_deterministically(): void
    {
        $result = $this->read($this->file("Data;Descrizione\n01/10/2026;Caff\xE8\n"));

        $this->assertSame('Caffè', $result['rows'][1][1]);
    }

    public function test_formula_looking_cells_stay_plain_text(): void
    {
        $result = $this->read($this->file("A,B\n=1+1,@SUM(A1)\n"));

        $this->assertSame(['=1+1', '@SUM(A1)'], $result['rows'][1]);
    }

    public function test_blank_lines_are_skipped_and_empty_files_rejected(): void
    {
        $this->assertCount(2, $this->read($this->file("A,B\n\n1,2\n\n"))['rows']);

        $this->expectException(ImportFileException::class);
        $this->read($this->file("\n\n"));
    }

    public function test_rejects_binary_content_posing_as_csv(): void
    {
        $this->expectException(ImportFileException::class);
        $this->read($this->file("A,B\n\0\0,1\n"));
    }

    public function test_rejects_other_extensions(): void
    {
        $this->expectException(ImportFileException::class);
        $this->read($this->file('a,b'), 'pdf');
    }

    public function test_rejects_a_zip_with_a_csv_name_and_text_with_an_xlsx_name(): void
    {
        $zipLike = $this->xlsx(fn ($sheet) => $sheet->setCellValue('A1', 'x'));
        try {
            $this->read($zipLike, 'csv');
            $this->fail('A workbook renamed to csv must be rejected.');
        } catch (ImportFileException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(ImportFileException::class);
        $this->read($this->file('a,b,c'), 'xlsx');
    }

    public function test_enforces_the_row_limit(): void
    {
        config(['import.max_rows' => 3]);

        $this->expectException(ImportFileException::class);
        $this->read($this->file("h\n1\n2\n3\n4\n"));
    }

    public function test_enforces_column_and_cell_length_limits(): void
    {
        config(['import.max_columns' => 3]);
        try {
            $this->read($this->file("a,b,c,d\n"));
            $this->fail('Too many columns must be rejected.');
        } catch (ImportFileException) {
            $this->addToAssertionCount(1);
        }

        config(['import.max_columns' => 40, 'import.max_cell_length' => 5]);
        $this->expectException(ImportFileException::class);
        $this->read($this->file("abcdefgh,b\n"));
    }

    public function test_enforces_the_file_size_limit(): void
    {
        config(['import.max_file_kb' => 1]);

        $this->expectException(ImportFileException::class);
        $this->read($this->file(str_repeat("a,b\n", 1000)));
    }

    public function test_reads_xlsx_values_dates_and_keeps_formulas_as_text(): void
    {
        $path = $this->xlsx(function ($sheet) {
            $sheet->fromArray([['Date', 'Amount', 'Memo']], null, 'A1');
            $sheet->setCellValue('A2', 46296); // Excel serial for 2026-10-01
            $sheet->getStyle('A2')->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $sheet->setCellValue('B2', -12.5);
            $sheet->setCellValue('C2', '=1+1');
            $sheet->getCell('C2')->setDataType('f');
            $sheet->setCellValue('A3', 'second sheet row');
        });

        $result = $this->read($path, 'xlsx');

        $this->assertSame('xlsx', $result['format']);
        $this->assertSame(['Date', 'Amount', 'Memo'], $result['rows'][0]);
        $this->assertSame('2026-10-01', $result['rows'][1][0]);
        $this->assertSame('-12.5', $result['rows'][1][1]);
        $this->assertSame('=1+1', $result['rows'][1][2], 'formulas are never evaluated');
    }

    public function test_xlsx_beyond_the_row_limit_is_rejected(): void
    {
        config(['import.max_rows' => 3]);
        $path = $this->xlsx(function ($sheet) {
            for ($row = 1; $row <= 10; $row++) {
                $sheet->setCellValue("A{$row}", "r{$row}");
            }
        });

        $this->expectException(ImportFileException::class);
        $this->read($path, 'xlsx');
    }

    public function test_xlsx_with_too_many_entries_is_rejected_as_unsafe(): void
    {
        config(['import.max_xlsx_entries' => 2]);
        $path = $this->xlsx(fn ($sheet) => $sheet->setCellValue('A1', 'x'));

        $this->expectException(ImportFileException::class);
        $this->read($path, 'xlsx');
    }

    public function test_xlsx_expanding_past_the_byte_limit_is_rejected(): void
    {
        config(['import.max_xlsx_uncompressed_bytes' => 100]);
        $path = $this->xlsx(fn ($sheet) => $sheet->setCellValue('A1', 'x'));

        $this->expectException(ImportFileException::class);
        $this->read($path, 'xlsx');
    }
}
