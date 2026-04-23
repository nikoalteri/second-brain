<?php

namespace App\Services;

use App\Exports\FinanceReport\FinanceReportWorkbookExport;
use App\Exports\FinanceReport\Sheets\FinanceReportSectionSheet;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

class FinanceReportExportService
{
    public function export(array $snapshot, string $format): Response
    {
        $year = $snapshot['selected_year'];

        return match ($format) {
            'csv' => $this->exportCsv($snapshot, $year),
            'xlsx' => $this->exportXlsx($snapshot, $year),
            'pdf' => $this->exportPdf($snapshot, $year),
        };
    }

    private function exportCsv(array $snapshot, int $year): Response
    {
        $rows = $this->stackedCsvRows($snapshot);
        $content = Excel::raw(
            new FinanceReportSectionSheet('Finance Report', $rows),
            ExcelFormat::CSV,
        );

        return response()->streamDownload(
            static fn () => print($content),
            "finance-report-{$year}.csv",
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function exportXlsx(array $snapshot, int $year): Response
    {
        $content = Excel::raw(
            new FinanceReportWorkbookExport($snapshot),
            ExcelFormat::XLSX,
        );

        return response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=finance-report-{$year}.xlsx",
        ]);
    }

    private function exportPdf(array $snapshot, int $year): Response
    {
        $content = Pdf::loadView('exports.finance-report', [
            'snapshot' => $snapshot,
        ])->output();

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=finance-report-{$year}.pdf",
        ]);
    }

    private function stackedCsvRows(array $snapshot): array
    {
        $rows = [];

        foreach ($snapshot['export_sections'] as $section) {
            $rows[] = [$section['heading']];

            foreach ($section['rows'] as $row) {
                $rows[] = $row;
            }

            $rows[] = [];
        }

        array_pop($rows);

        return $rows;
    }
}
