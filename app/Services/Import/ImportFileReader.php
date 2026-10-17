<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
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
 * is read. Every limit is checked before the content is staged, and content beyond a limit is
 * rejected rather than silently dropped.
 */
class ImportFileReader
{
    public const DELIMITERS = ['comma' => ',', 'semicolon' => ';', 'tab' => "\t", 'pipe' => '|'];

    /** Blank rows/columns tolerated around the data before the content counts as out of bounds. */
    private const BLANK_ROW_SLACK = 50;
    private const BLANK_COLUMN_SLACK = 10;
    private const DELIMITER_SAMPLE_RECORDS = 25;
    private const DELIMITER_CONSISTENCY = 0.9;

    /**
     * @param string|null $delimiter one of DELIMITERS' keys; null detects it from the record structure
     * @return array{format: string, rows: list<list<string>>, numeric: list<list<int>>}
     *         `numeric` lists, per row, the columns that held a real XLSX number (never text), so a
     *         configured decimal/thousands separator is not applied to a value that is already numeric.
     */
    public function read(string $path, string $extension, ?string $delimiter = null): array
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
                ? ['format' => 'xlsx', ...$this->readXlsx($path)]
                : throw new ImportFileException('The file is not a valid XLSX workbook.'),
            'csv' => $isZip
                ? throw new ImportFileException('The file content does not match a CSV file.')
                : ['format' => 'csv', ...$this->readCsv($path, $delimiter)],
            default => throw new ImportFileException('Only CSV and XLSX files can be imported.'),
        };
    }

    /**
     * @return array{rows: list<list<string>>, numeric: list<list<int>>}
     */
    private function readCsv(string $path, ?string $delimiterName): array
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

        $delimiter = $this->resolveDelimiter($content, $delimiterName);
        $stream = $this->stream($content);

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

        $rows = $this->finish($rows);

        return ['rows' => $rows, 'numeric' => array_fill(0, count($rows), [])];
    }

    /**
     * The delimiter is the one under which the records of the file split into the same number of
     * columns (quotes respected), not the most frequent character: commas inside numbers and
     * descriptions must not decide it. Two candidates that both fit are ambiguous and are never guessed.
     */
    private function resolveDelimiter(string $content, ?string $name): string
    {
        if ($name !== null) {
            return self::DELIMITERS[$name] ?? throw new ImportFileException('Unknown delimiter.');
        }

        $fitting = [];

        foreach (self::DELIMITERS as $character) {
            $counts = $this->columnCounts($content, $character);

            if ($counts === []) {
                continue;
            }

            $frequency = array_count_values($counts);
            arsort($frequency);
            $modal = (int) array_key_first($frequency);

            if ($modal >= 2 && ($frequency[$modal] / count($counts)) >= self::DELIMITER_CONSISTENCY) {
                $fitting[$character] = $modal;
            }
        }

        if (count($fitting) > 1) {
            throw new ImportFileException('The delimiter is ambiguous; choose it explicitly (comma, semicolon, tab or pipe).');
        }

        // A single column (no candidate splits it consistently) reads the same with any delimiter.
        return array_key_first($fitting) ?? ',';
    }

    /**
     * @return list<int> number of columns of the first records under a candidate delimiter
     */
    private function columnCounts(string $content, string $delimiter): array
    {
        $stream = $this->stream($content);
        $counts = [];

        while (count($counts) < self::DELIMITER_SAMPLE_RECORDS && ($cells = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            if ($cells !== [null]) {
                $counts[] = count($cells);
            }
        }

        fclose($stream);

        return $counts;
    }

    /** @return resource */
    private function stream(string $content)
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        return $stream;
    }

    /**
     * @return array{rows: list<list<string>>, numeric: list<list<int>>}
     */
    private function readXlsx(string $path): array
    {
        $this->assertSafeArchive($path);

        $rowBound = (int) config('import.max_rows') + 1 + self::BLANK_ROW_SLACK;
        $columnBound = (int) config('import.max_columns') + self::BLANK_COLUMN_SLACK;

        $filter = new XlsxBoundsFilter($rowBound, $columnBound, inside: true);
        $sheet = $this->loadSheet($path, $filter);

        // Cells outside the window were skipped, not read. Look at them separately so content past a
        // limit is rejected instead of silently disappearing (an empty row in between must not hide it).
        if ($filter->skipped) {
            $this->assertNothingOutside($path, $rowBound, $columnBound);
        }

        $rows = [];
        $numericColumns = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];
            $numeric = [];
            $iterator = $row->getCellIterator();
            $iterator->setIterateOnlyExistingCells(false);

            foreach ($iterator as $cell) {
                [$text, $isNumber] = $this->cellToString($cell);

                if ($isNumber) {
                    $numeric[] = count($cells);
                }

                $cells[] = $text;
            }

            while ($cells !== [] && end($cells) === '') {
                array_pop($cells);
            }

            if ($cells === []) {
                continue;
            }

            $rows[] = $this->cleanRow($cells, count($rows) + 1);
            $numericColumns[] = array_values(array_filter($numeric, fn (int $position) => $position < count($cells)));
        }

        // Every kept row is non-empty, so `finish` removes nothing and the two lists stay aligned.
        return ['rows' => $this->finish($rows), 'numeric' => $numericColumns];
    }

    private function assertNothingOutside(string $path, int $rowBound, int $columnBound): void
    {
        $outside = $this->loadSheet($path, new XlsxBoundsFilter($rowBound, $columnBound, inside: false));

        foreach ($outside->getCellCollection()->getCoordinates() as $coordinate) {
            $value = $outside->getCell($coordinate)->getValue();

            if ($value !== null && trim((string) $value) !== '') {
                throw new ImportFileException('The workbook has content beyond the supported number of rows or columns.');
            }
        }
    }

    /**
     * @return \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet
     */
    private function loadSheet(string $path, IReadFilter $filter)
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(false); // styles are needed to tell dates from plain numbers
            $reader->setReadFilter($filter);
            $reader->setLoadSheetsOnly([$reader->listWorksheetNames($path)[0] ?? '']);

            return $reader->load($path)->getSheet(0);
        } catch (Throwable) {
            throw new ImportFileException('The workbook could not be read.');
        }
    }

    /**
     * @return array{0: string, 1: bool} the cell text and whether it was a real number
     */
    private function cellToString(Cell $cell): array
    {
        $value = $cell->getValue();

        if ($value === null) {
            return ['', false];
        }

        // The cached/typed value only: getCalculatedValue() would evaluate the formula.
        if (is_numeric($value) && $cell->getDataType() === DataType::TYPE_NUMERIC) {
            if (Date::isDateTime($cell)) {
                return [Date::excelToDateTimeObject((float) $value)->format('Y-m-d'), false];
            }

            $text = is_float($value) ? rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.') : (string) $value;

            return [$text, true];
        }

        return [trim((string) $value), false];
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
