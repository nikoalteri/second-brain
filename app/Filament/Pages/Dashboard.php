<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\NetWorthWidget::class,
            \App\Filament\Widgets\TotalDebtsWidget::class,
            \App\Filament\Widgets\SubscriptionsStatsWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            \App\Filament\Widgets\BudgetAlertsWidget::class,
            \App\Filament\Widgets\ExpensesByCategoryChart::class,
            \App\Filament\Widgets\NetWorthTrendChart::class,
            \App\Filament\Widgets\MonthlyCashflowChart::class,
            \App\Filament\Widgets\UpcomingRenewalsWidget::class,
            \App\Filament\Widgets\UpcomingPaymentsWidget::class,
        ];
    }
}
