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

    // ─── review fixes ───────────────────────────────────────────────────────────────────────

    public function test_xlsx_numeric_cells_are_reported_so_text_separators_never_apply_to_them(): void
    {
        $path = $this->xlsx(function ($sheet) {
            $sheet->fromArray([['Date', 'Amount', 'Text amount']], null, 'A1');
            $sheet->setCellValue('A2', '2026-10-01');
            $sheet->setCellValue('B2', -12.5);
            $sheet->setCellValueExplicit('C2', '1.234,56', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        });

        $result = $this->read($path, 'xlsx');

        $this->assertSame([1], $result['numeric'][1], 'only the real number is flagged numeric');
        $this->assertSame('-12.5', $result['rows'][1][1]);
        $this->assertSame('1.234,56', $result['rows'][1][2]);
        $this->assertSame([[], [1]], array_slice($result['numeric'], 0, 2));
    }

    public function test_xlsx_content_past_the_row_limit_is_rejected_even_behind_an_empty_row(): void
    {
        config(['import.max_rows' => 3]);
        $path = $this->xlsx(function ($sheet) {
            $sheet->setCellValue('A1', 'Header');
            foreach ([2, 3, 4] as $row) {
                $sheet->setCellValue("A{$row}", "r{$row}");
            }
            $sheet->setCellValue('A6', 'hidden fourth data row'); // A5 left empty on purpose
        });

        $this->expectException(ImportFileException::class);
        $this->read($path, 'xlsx');
    }

    public function test_xlsx_content_far_past_the_filter_is_detected_not_silently_dropped(): void
    {
        config(['import.max_rows' => 3]);
        $path = $this->xlsx(function ($sheet) {
            $sheet->setCellValue('A1', 'Header');
            $sheet->setCellValue('A2', 'one');
            $sheet->setCellValue('A500', 'far away');
        });

        $this->expectException(ImportFileException::class);
        $this->read($path, 'xlsx');
    }

    public function test_xlsx_content_in_columns_past_the_limit_is_rejected(): void
    {
        config(['import.max_columns' => 3]);
        $path = $this->xlsx(function ($sheet) {
            $sheet->fromArray([['a', 'b', 'c']], null, 'A1');
            $sheet->setCellValue('Z1', 'sparse far column');
        });

        $this->expectException(ImportFileException::class);
        $this->read($path, 'xlsx');
    }

    public function test_xlsx_blank_rows_inside_the_data_do_not_cause_a_false_rejection(): void
    {
        config(['import.max_rows' => 3]);
        $path = $this->xlsx(function ($sheet) {
            $sheet->setCellValue('A1', 'Header');
            $sheet->setCellValue('A2', 'one');
            $sheet->setCellValue('A5', 'two'); // two blank rows between data
            $sheet->setCellValue('A6', 'three');
        });

        $this->assertCount(4, $this->read($path, 'xlsx')['rows']);
    }

    public function test_csv_delimiter_comes_from_the_record_structure_not_from_character_counts(): void
    {
        $result = $this->read($this->file("Date;Amount;Description\n2026-10-01;-12,50;\"Shop, Rome, Italy, card, memo\"\n2026-10-02;-3,00;Tea, Milan, extra, words, here\n"));

        $this->assertSame(['Date', 'Amount', 'Description'], $result['rows'][0]);
        $this->assertCount(3, $result['rows'][1]);
        $this->assertCount(3, $result['rows'][2], 'unquoted commas in a semicolon file stay inside the description');
        $this->assertSame('Tea, Milan, extra, words, here', $result['rows'][2][2]);
    }

    public function test_an_ambiguous_csv_asks_for_an_explicit_delimiter_and_honours_it(): void
    {
        // Every line splits consistently on both ';' (3 columns) and ',' (2 columns).
        $content = "2026-10-01;-12,50;Coffee\n2026-10-02;-3,00;Tea\n2026-10-03;-4,00;Milk\n";
        $path = $this->file($content);

        try {
            $this->read($path);
            $this->fail('An ambiguous delimiter must not be guessed.');
        } catch (ImportFileException $e) {
            $this->assertStringContainsString('delimiter', $e->getMessage());
        }

        $semicolon = app(ImportFileReader::class)->read($path, 'csv', 'semicolon');
        $this->assertSame(['2026-10-01', '-12,50', 'Coffee'], $semicolon['rows'][0]);

        $comma = app(ImportFileReader::class)->read($path, 'csv', 'comma');
        $this->assertSame(['2026-10-01;-12', '50;Coffee'], $comma['rows'][0]);
    }

    public function test_single_column_files_and_tab_or_pipe_delimiters_still_work(): void
    {
        $this->assertSame([['h'], ['1']], $this->read($this->file("h\n1\n"))['rows']);
        $this->assertSame(['a', 'b', 'c'], $this->read($this->file("a\tb\tc\n1\t2\t3\n"))['rows'][0]);
        $this->assertSame(['a', 'b', 'c'], $this->read($this->file("a|b|c\n1|2|3\n"))['rows'][0]);
    }
}
