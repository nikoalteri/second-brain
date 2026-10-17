<?php

namespace App\Services\Import;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Admits only the cells inside (or, inverted, only those outside) a row/column window, and records
 * whether it had to skip any, so a reader can tell that more content exists than it loaded.
 */
class XlsxBoundsFilter implements IReadFilter
{
    public bool $skipped = false;

    public function __construct(
        private readonly int $maxRow,
        private readonly int $maxColumn,
        private readonly bool $inside,
    ) {}

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        $within = $row <= $this->maxRow && Coordinate::columnIndexFromString($columnAddress) <= $this->maxColumn;
        $admit = $within === $this->inside;

        if (! $admit) {
            $this->skipped = true;
        }

        return $admit;
    }
}
