<?php

namespace App\Exports\FinanceReport\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class FinanceReportSectionSheet implements FromArray, WithTitle
{
    public function __construct(
        private readonly string $title,
        private readonly array $rows,
    ) {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function title(): string
    {
        return $this->title;
    }
}
