<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Throwable;
use ZipArchive;

/**
 * Reads a CSV or XLSX statement into plain string cells.
 *
 * Nothing in the file is ever executed or followed: CSV is text, and in XLSX a formula cell is kept
 * as its formula text (never calculated), external links are not resolved and only the first sheet
 * is read. Every limit is checked before the content is staged.
 */
class ImportFileReader
{
    /**
     * @return array{format: string, rows: list<list<string>>}
     */
    public function read(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if (! is_file($path) || filesize($path) === 0) {
            throw new ImportFileException('The file is empty.');
        }

        if (filesize($path) > config('import.max_file_kb') * 1024) {
            throw new ImportFileException('The file is too large.');
        }

        $head = (string) file_get_contents($path, false, null, 0, 4);
        $isZip = str_starts_with($head, "PK\x03\x04");

        return match ($extension) {
            'xlsx' => $isZip
                ? ['format' => 'xlsx', 'rows' => $this->readXlsx($path)]
                : throw new ImportFileException('The file is not a valid XLSX workbook.'),
            'csv' => $isZip
                ? throw new ImportFileException('The file content does not match a CSV file.')
                : ['format' => 'csv', 'rows' => $this->readCsv($path)],
            default => throw new ImportFileException('Only CSV and XLSX files can be imported.'),
        };
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);

        if (str_contains($content, "\0")) {
            throw new ImportFileException('The file is not a text CSV file.');
        }

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Bank exports are often Windows-1252: convert deterministically instead of guessing a locale.
        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $delimiter = $this->detectDelimiter($content);
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];

        while (($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if ($cells === [null]) {
                continue; // blank line
            }

            $rows[] = $this->cleanRow(array_map(fn ($cell) => (string) $cell, $cells), count($rows) + 1);

            if (count($rows) > config('import.max_rows') + 1) {
                fclose($stream);
                throw new ImportFileException('The file has too many rows.');
            }
        }

        fclose($stream);

        return $this->finish($rows);
    }

    private function detectDelimiter(string $content): string
    {
        $sample = implode("\n", array_slice(preg_split('/\r\n|\n|\r/', $content) ?: [], 0, 5));
        $best = ',';
        $bestCount = -1;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = substr_count($sample, $candidate);

            if ($count > $bestCount) {
                $best = $candidate;
                $bestCount = $count;
            }
        }

        return $best;
    }

    /**
     * @return list<list<string>>
     */
    private function readXlsx(string $path): array
    {
        $this->assertSafeArchive($path);

        $maxRows = (int) config('import.max_rows');
        $maxColumns = (int) config('import.max_columns');

        $filter = new class($maxRows + 2, $maxColumns + 1) implements IReadFilter
        {
            public function __construct(private int $rows, private int $columns) {}

            public function readCell($columnAddress, $row, $worksheetName = ''): bool
            {
                return $row <= $this->rows
                    && Coordinate::columnIndexFromString($columnAddress) <= $this->columns;
            }
        };

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false); // styles are needed to tell dates from plain numbers
            $reader->setReadFilter($filter);
            $reader->setLoadSheetsOnly([$reader->listWorksheetNames($path)[0] ?? '']);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (Throwable) {
            throw new ImportFileException('The workbook could not be read.');
        }

        $rows = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            $iterator = $row->getCellIterator();
            $iterator->setIterateOnlyExistingCells(false);

            foreach ($iterator as $cell) {
                $cells[] = $this->cellToString($cell);
            }

            while ($cells !== [] && end($cells) === '') {
                array_pop($cells);
            }

            if ($cells === []) {
                continue;
            }

            $rows[] = $this->cleanRow($cells, count($rows) + 1);
        }

        return $this->finish($rows);
    }

    private function cellToString(Cell $cell): string
    {
        $value = $cell->getValue();

        if ($value === null) {
            return '';
        }

        // The cached/typed value only: getCalculatedValue() would evaluate the formula.
        if (is_numeric($value) && Date::isDateTime($cell)) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        if (is_float($value)) {
            return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
        }

        return trim((string) $value);
    }

    private function assertSafeArchive(string $path): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new ImportFileException('The file is not a valid XLSX workbook.');
        }

        if ($zip->numFiles > config('import.max_xlsx_entries')) {
            $zip->close();
            throw new ImportFileException('The workbook is too complex to import.');
        }

        $total = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $total += (int) ($zip->statIndex($i)['size'] ?? 0);

            if ($total > config('import.max_xlsx_uncompressed_bytes')) {
                $zip->close();
                throw new ImportFileException('The workbook expands to an unsafe size.');
            }
        }

        $zip->close();
    }

    /**
     * @param list<string> $cells
     * @return list<string>
     */
    private function cleanRow(array $cells, int $rowNumber): array
    {
        while ($cells !== [] && end($cells) === '') {
            array_pop($cells);
        }

        if (count($cells) > config('import.max_columns')) {
            throw new ImportFileException("Row {$rowNumber} has too many columns.");
        }

        foreach ($cells as $index => $cell) {
            $cell = trim($cell);

            if (mb_strlen($cell) > config('import.max_cell_length')) {
                throw new ImportFileException("Row {$rowNumber} has a cell that is too long.");
            }

            $cells[$index] = $cell;
        }

        return $cells;
    }

    /**
     * @param list<list<string>> $rows
     * @return list<list<string>>
     */
    private function finish(array $rows): array
    {
        $rows = array_values(array_filter($rows, fn (array $row) => $row !== []));

        if ($rows === []) {
            throw new ImportFileException('The file has no data rows.');
        }

        if (count($rows) > config('import.max_rows') + 1) {
            throw new ImportFileException('The file has too many rows.');
        }

        return $rows;
    }
}
