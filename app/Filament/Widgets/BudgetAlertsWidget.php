<?php

namespace App\Filament\Widgets;

use App\Services\BudgetService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class BudgetAlertsWidget extends Widget
{
    protected string $view = 'filament.widgets.budget-alerts-widget';
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $overview = app(BudgetService::class)->getMonthlyOverview(
            Auth::id(),
            (int) now()->year,
            (int) now()->month,
        );

        $alerts = collect($overview['categories'])
            ->filter(fn (array $category): bool => in_array($category['alert_status'], ['warning', 'exceeded', 'critical'], true))
            ->values()
            ->all();

        return [
            'alerts' => $alerts,
            'selectedMonthLabel' => now()->translatedFormat('F Y'),
        ];
    }
}
