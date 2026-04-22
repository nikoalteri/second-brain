<?php

namespace App\Filament\Pages;

use Carbon\Carbon;
use Filament\Pages\Page;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;

class FinanceReport extends Page
{
    protected string $view = 'filament.pages.finance-report';

    public int $selectedYear = 2026;
    public array $years = [];

    public function getTitle(): string
    {
        return 'Finance Reports';
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-chart-bar';
    }

    public static function getNavigationLabel(): string
    {
        return 'Finance Reports';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Finance';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }
    public array $selectedTypes = [];

    /** @var string|null Filtro Note - null = Tutto */
    public ?string $selectedNote = null;

    /** @var array<string> Categorie espanse (mostra sottocategorie) */
    public array $expandedCategories = [];

    /** Modal dettaglio transazioni */
    public bool $showDetailModal = false;
    public ?int $detailMonth = null;
    public ?string $detailCategoryKey = null;
    public string $detailCategoryLabel = '';

    public function mount(): void
    {
        $this->years = app(\App\Services\FinanceReportService::class)
            ->loadYears(Auth::id());
        $this->selectedYear = $this->years[0] ?? now()->year;
    }

    public function getHeaderActions(): array
    {
        return [];
    }

    public function loadData(): void
    {
        $this->years = app(\App\Services\FinanceReportService::class)
            ->loadYears(Auth::id());
    }

    private function loadYears(): void
    {
        // Metodo non più usato: delegato al service
    }

    /** Query base con filtri applicati */
    private function baseTransactionsQuery(): Builder
    {
        return app(\App\Services\FinanceReportService::class)
            ->baseTransactionsQuery(
                $this->selectedYear,
                $this->selectedTypes,
                $this->selectedNote,
                Auth::id()
            );
    }

    public function getTypeOptions(): array
    {
        return app(\App\Services\FinanceReportService::class)
            ->getTypeOptions();
    }

    public function toggleExpand(string $category): void
    {
        if (in_array($category, $this->expandedCategories)) {
            $this->expandedCategories = array_values(array_diff($this->expandedCategories, [$category]));
        } else {
            $this->expandedCategories = array_values(array_merge($this->expandedCategories, [$category]));
        }
    }

    public function openDetail(int $month, string $categoryKey, string $categoryLabel): void
    {
        $this->detailMonth = $month;
        $this->detailCategoryKey = $categoryKey;
        $this->detailCategoryLabel = $categoryLabel;
        $this->showDetailModal = true;
    }

    public function closeDetail(): void
    {
        $this->showDetailModal = false;
    }

    /** Transazioni per il dettaglio (mese + categoria) */
    public function getDetailTransactions(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->detailMonth === null || $this->detailCategoryKey === null) {
            return collect();
        }

        return app(\App\Services\FinanceReportService::class)
            ->getDetailTransactions(
                $this->selectedYear,
                $this->detailMonth,
                $this->detailCategoryKey,
                $this->selectedTypes,
                $this->selectedNote,
                Auth::id(),
            );
    }

    public function getNoteOptions(): array
    {
        return app(\App\Services\FinanceReportService::class)
            ->getNoteOptions($this->selectedYear, Auth::id());
    }

    // ─── Simple table (Cashflow) ────────────────────────────────────────────
    public function getTable(): array
    {
        return app(\App\Services\FinanceReportService::class)
            ->getTable($this->selectedYear, Auth::id());
    }

    // ─── Pivot per categoria × mese (gerarchia parent/children) ───────────
    public function getPivotData(): array
    {
        return app(\App\Services\FinanceReportService::class)
            ->getPivotData($this->selectedYear, $this->selectedTypes, $this->selectedNote, Auth::id());
    }


    // ─── Dati grafico torta (solo livello parent) ──────────────────────────
    public function getPieData(): array
    {
        return app(\App\Services\FinanceReportService::class)
            ->getPieData($this->selectedYear, $this->selectedTypes, $this->selectedNote, Auth::id());
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $pivotData = $this->getPivotData();
        $tree = $pivotData['tree'];
        $pivot = $pivotData['pivot'];

        $csv = "Finance Report - Year {$this->selectedYear}\n";
        $csv .= "Category";
        for ($m = 1; $m <= 12; $m++) {
            $csv .= "," . Carbon::create(2026, $m, 1)->format('M');
        }
        $csv .= ",Total\n";

        foreach ($tree as $parent) {
            $parentData = $pivot[$parent['key']] ?? [];
            $csv .= $parent['label'];
            for ($m = 1; $m <= 12; $m++) {
                $csv .= "," . number_format($parentData[$m] ?? 0, 2, '.', '');
            }
            $csv .= "," . number_format($parentData['total'] ?? 0, 2, '.', '') . "\n";

            if ($parent['has_children']) {
                foreach ($parent['children'] as $child) {
                    $childData = $pivot[$child['key']] ?? [];
                    $csv .= "  " . $child['label'];
                    for ($m = 1; $m <= 12; $m++) {
                        $csv .= "," . number_format($childData[$m] ?? 0, 2, '.', '');
                    }
                    $csv .= "," . number_format($childData['total'] ?? 0, 2, '.', '') . "\n";
                }
            }
        }

        return response()->streamDownload(
            fn() => print($csv),
            "finance-report-{$this->selectedYear}.csv",
            ['Content-Type' => 'text/csv']
        );
    }

    public function exportPdf(): void
    {
        // TODO: Implement PDF export using barryvdh/laravel-dompdf
        session()->flash('warning', 'PDF export coming soon! Please use CSV export for now.');
    }
}
