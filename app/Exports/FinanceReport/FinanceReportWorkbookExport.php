<?php

namespace App\Exports\FinanceReport;

use App\Exports\FinanceReport\Sheets\FinanceReportSectionSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class FinanceReportWorkbookExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $snapshot,
    ) {
    }

    public function sheets(): array
    {
        return [
            new FinanceReportSectionSheet('Cashflow', $this->snapshot['xlsx_sections']['cashflow']),
            new FinanceReportSectionSheet('Category Pivot', $this->snapshot['xlsx_sections']['pivot']),
            new FinanceReportSectionSheet('Distribution', $this->snapshot['xlsx_sections']['distribution']),
        ];
    }
}
