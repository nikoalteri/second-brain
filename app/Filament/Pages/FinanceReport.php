<?php

namespace App\Filament\Pages;

use App\Models\Transaction;
use App\Models\TransactionType;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class FinanceReport extends Page
{
    protected string $view = 'filament.pages.finance-report';
    protected static bool $shouldRegisterNavigation = false;

    public int $selectedYear = 2026;
    public array $years = [];

    /** @var array<int> Filtro Tipologia (transaction_type_id) - vuoto = tutti */
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
        return [
            Action::make('refresh')
                ->label('Aggiorna')
                ->icon('heroicon-o-arrow-path')
                ->action('loadData'),
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

        $query = Transaction::query()
            ->with(['account', 'type', 'category.parent'])
            ->where('is_transfer', false)
            ->whereNull('deleted_at')
            ->whereYear('date', $this->selectedYear)
            ->whereMonth('date', $this->detailMonth)
            ->when(Auth::id(), fn($q) => $q->where('user_id', Auth::id()));

        if (!empty($this->selectedTypes)) {
            $query->whereIn('transaction_type_id', $this->selectedTypes);
        }
        if ($this->selectedNote !== null && $this->selectedNote !== '') {
            $query->where('notes', $this->selectedNote);
        }

        if (str_contains($this->detailCategoryKey, '|')) {
            [$parentName, $childName] = explode('|', $this->detailCategoryKey, 2);
            $query->whereHas('category', function (EloquentBuilder $q) use ($parentName, $childName) {
                $q->where('name', $childName)
                    ->whereHas('parent', fn($pq) => $pq->where('name', $parentName));
            });
        } else {
            $parentName = $this->detailCategoryKey;
            if ($parentName === 'Altro') {
                $query->whereNull('transaction_category_id');
            } else {
                $query->whereHas('category', function (EloquentBuilder $q) use ($parentName) {
                    $q->whereHas('parent', fn($pq) => $pq->where('name', $parentName));
                });
            }
        }

        return $query->orderBy('date')->orderBy('id')->get();
    }

    public function getNoteOptions(): array
    {
        $q = DB::table('transactions')
            ->select('notes')
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->whereYear('date', $this->selectedYear)
            ->when(Auth::id(), fn($query) => $query->where('user_id', Auth::id()));

        return $q->distinct()->orderBy('notes')->limit(100)->pluck('notes', 'notes')->toArray();
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
        $rows = $this->baseTransactionsQuery()
            ->leftJoin('transaction_categories as tc', 'transactions.transaction_category_id', '=', 'tc.id')
            ->leftJoin('transaction_categories as tcp', 'tc.parent_id', '=', 'tcp.id')
            ->selectRaw('
                MONTH(transactions.date) as month,
                COALESCE(tcp.name, tc.name, "Altro") as parent_name,
                CASE WHEN tc.parent_id IS NOT NULL THEN tc.name ELSE NULL END as child_name,
                SUM(transactions.amount) as amount
            ')
            ->groupByRaw('MONTH(transactions.date), COALESCE(tcp.name, tc.name, "Altro"), CASE WHEN tc.parent_id IS NOT NULL THEN tc.name ELSE NULL END')
            ->get();

        $pivot = [];
        $childrenByParent = [];
        $monthTotals = array_fill(1, 12, 0);

        foreach ($rows as $row) {
            $parentName = $row->parent_name;
            $childName = $row->child_name;
            $key = $childName ? "{$parentName}|{$childName}" : $parentName;

            $pivot[$key][$row->month] = $row->amount;
            $pivot[$key]['total'] = ($pivot[$key]['total'] ?? 0) + $row->amount;
            $monthTotals[$row->month] += $row->amount;

            if ($childName) {
                if (!isset($childrenByParent[$parentName])) {
                    $childrenByParent[$parentName] = [];
                }
                if (!in_array($childName, $childrenByParent[$parentName])) {
                    $childrenByParent[$parentName][] = $childName;
                }
            }
        }

        // Costruisci albero: parent con children, ordinato per totale assoluto
        $parentTotals = [];
        foreach ($pivot as $key => $data) {
            $parentName = str_contains($key, '|') ? explode('|', $key)[0] : $key;
            $parentTotals[$parentName] = ($parentTotals[$parentName] ?? 0) + ($data['total'] ?? 0);
        }
        uasort($parentTotals, fn($a, $b) => abs($b) <=> abs($a));

        $tree = [];
        foreach (array_keys($parentTotals) as $parentName) {
            $children = [];
            foreach ($childrenByParent[$parentName] ?? [] as $childName) {
                $childKey = "{$parentName}|{$childName}";
                if (isset($pivot[$childKey])) {
                    $children[] = ['key' => $childKey, 'label' => $childName];
                }
            }
            usort($children, fn($a, $b) => abs($pivot[$b['key']]['total'] ?? 0) <=> abs($pivot[$a['key']]['total'] ?? 0));
            $tree[] = [
                'key'         => $parentName,
                'label'       => $parentName,
                'has_children' => !empty($children),
                'children'    => $children,
            ];
        }

        return [
            'tree'        => $tree,
            'pivot'       => $pivot,
            'monthTotals' => $monthTotals,
            'grandTotal'  => array_sum($monthTotals),
        ];
    }


    // ─── Dati grafico torta (solo livello parent) ──────────────────────────
    public function getPieData(): array
    {
        $pivot = $this->getPivotData();
        $data  = [];

        foreach ($pivot['tree'] as $node) {
            $total = $pivot['pivot'][$node['key']]['total'] ?? 0;
            if ($node['has_children']) {
                foreach ($node['children'] as $child) {
                    $total += $pivot['pivot'][$child['key']]['total'] ?? 0;
                }
            }
            if ($total != 0) {
                $data[] = [
                    'label'  => $node['label'],
                    'amount' => round(abs($total), 2),
                ];
            }
        }

        return $data;
    }
}
