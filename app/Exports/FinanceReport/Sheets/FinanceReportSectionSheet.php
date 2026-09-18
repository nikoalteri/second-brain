<?php

namespace App\Exports\FinanceReport\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

/**
 * Text in these sheets comes from users (category names and the like), and a spreadsheet
 * evaluates a cell that starts with = + - @ as a formula. Two defences, one per format:
 *  - XLSX: text is stored with an explicit string type, so it is never parsed as a formula;
 *  - CSV has no cell types, so text that could be read as a formula is prefixed with an
 *    apostrophe (the OWASP recommendation). Numbers are left alone.
 */
class FinanceReportSectionSheet extends DefaultValueBinder implements FromArray, WithCustomValueBinder, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly array $rows,
        private readonly bool $neutraliseFormulas = false,
    ) {}

    public function array(): array
    {
        if (! $this->neutraliseFormulas) {
            return $this->rows;
        }

        return array_map(
            fn (array $row) => array_map(fn ($value) => self::neutralise($value), $row),
            $this->rows
        );
    }

    public function title(): string
    {
        return $this->title;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (is_string($value) && ! is_numeric($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    private static function neutralise(mixed $value): mixed
    {
        if (is_string($value) && ! is_numeric($value) && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
