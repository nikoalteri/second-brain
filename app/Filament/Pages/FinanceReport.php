<?php

namespace App\Filament\Pages;

use App\Services\BudgetService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FinanceReport extends Page
{
    protected string $view = 'filament.pages.finance-report';

    public int $selectedYear = 2026;
    public int $selectedBudgetMonth = 1;
    public array $years = [];
    public array $budgetInputs = [];

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
        $this->selectedBudgetMonth = (int) now()->month;
    }

    public function getHeaderActions(): array
    {
        return [
            Action::make('exportCsv')
                ->label('CSV')
                ->url(fn (): string => $this->getExportUrl('csv'), shouldOpenInNewTab: true),
            Action::make('exportXlsx')
                ->label('XLSX')
                ->url(fn (): string => $this->getExportUrl('xlsx'), shouldOpenInNewTab: true),
            Action::make('exportPdf')
                ->label('PDF')
                ->url(fn (): string => $this->getExportUrl('pdf'), shouldOpenInNewTab: true),
        ];
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

    public function getBudgetOverview(): array
    {
        $overview = app(BudgetService::class)->getMonthlyOverview(
            Auth::id(),
            $this->selectedYear,
            $this->selectedBudgetMonth,
        );

        foreach ($overview['categories'] as $category) {
            $this->budgetInputs[$category['transaction_category_id']] ??= $category['budget_amount'] === null
                ? ''
                : number_format((float) $category['budget_amount'], 2, '.', '');
        }

        return $overview;
    }

    public function saveBudget(int $transactionCategoryId): void
    {
        $value = $this->budgetInputs[$transactionCategoryId] ?? null;

        if ($value === null || $value === '') {
            throw ValidationException::withMessages([
                "budgetInputs.$transactionCategoryId" => 'Budget amount is required.',
            ]);
        }

        if (! is_numeric($value) || (float) $value <= 0) {
            throw ValidationException::withMessages([
                "budgetInputs.$transactionCategoryId" => 'Budget amount must be greater than zero.',
            ]);
        }

        app(BudgetService::class)->upsertMonthlyBudget(
            Auth::id(),
            $transactionCategoryId,
            $this->selectedYear,
            $this->selectedBudgetMonth,
            (float) $value,
        );
    }

    public function clearBudget(int $transactionCategoryId): void
    {
        app(BudgetService::class)->clearMonthlyBudget(
            Auth::id(),
            $transactionCategoryId,
            $this->selectedYear,
            $this->selectedBudgetMonth,
        );

        $this->budgetInputs[$transactionCategoryId] = '';
    }

    public function getExportUrl(string $format): string
    {
        return url('/api/v1/reports/finance/export?' . http_build_query(array_filter([
            'year' => $this->selectedYear,
            'types' => $this->selectedTypes,
            'note' => $this->selectedNote,
            'format' => $format,
        ], static fn (mixed $value): bool => $value !== null && $value !== [] && $value !== '')));
    }

    public function getBudgetStatusColor(string $status): string
    {
        return match ($status) {
            'warning' => '#d97706',
            'exceeded' => '#dc2626',
            'critical' => '#991b1b',
            'ok' => '#16a34a',
            default => '#6b7280',
        };
    }

    public function getBudgetUsageLabel(?float $usageRatio): string
    {
        if ($usageRatio === null) {
            return '—';
        }

        return number_format($usageRatio * 100, 1, '.', '') . '%';
    }
}
