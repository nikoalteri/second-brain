<?php

namespace App\Filament\Widgets;

use App\Services\CreditCardKpiService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CreditCardsKpiOverview extends BaseWidget
{
    protected null|string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $userId = Auth::id();

        if (! $userId) {
            return [];
        }

        $kpis = app(CreditCardKpiService::class)->getForUser($userId);

        $nextDueDescription = $kpis['next_due_amount'] > 0
            ? ($kpis['next_due_date']
                ? 'Due ' . $kpis['next_due_date']->format('d/m/Y')
                : 'Pending payment without due date')
            : 'No pending payment';

        return [
            Stat::make('Cards spent this month', $this->formatCurrency($kpis['spent_this_month']))
                ->icon('heroicon-m-shopping-bag')
                ->description('Tracked card expenses in current month')
                ->descriptionColor('info')
                ->color('info'),

            Stat::make('Next card payment', $this->formatCurrency($kpis['next_due_amount']))
                ->icon('heroicon-m-calendar-days')
                ->description($nextDueDescription)
                ->descriptionColor('warning')
                ->color('warning'),

            Stat::make('Revolving residual', $this->formatCurrency($kpis['revolving_residual']))
                ->icon('heroicon-m-arrow-path-rounded-square')
                ->description('Outstanding revolving principal')
                ->descriptionColor($kpis['revolving_residual'] > 0 ? 'danger' : 'success')
                ->color($kpis['revolving_residual'] > 0 ? 'danger' : 'success'),

            Stat::make('Available credit (limited cards)', $this->formatCurrency($kpis['total_available_limited']))
                ->icon('heroicon-m-credit-card')
                ->description(($kpis['unlimited_cards_count'] > 0)
                    ? ('Unlimited cards: ' . $kpis['unlimited_cards_count'])
                    : 'No unlimited cards')
                ->descriptionColor('success')
                ->color('success'),

            Stat::make('Overdue cycles', (string) $kpis['overdue_cycles_count'])
                ->icon('heroicon-m-exclamation-triangle')
                ->description('Open overdue amount: ' . $this->formatCurrency($kpis['overdue_total_due']))
                ->descriptionColor($kpis['overdue_cycles_count'] > 0 ? 'danger' : 'success')
                ->color($kpis['overdue_cycles_count'] > 0 ? 'danger' : 'success'),
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return Number::currency($amount, 'EUR', locale: 'it');
    }
}
