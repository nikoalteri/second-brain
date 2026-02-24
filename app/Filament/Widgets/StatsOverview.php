<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected null|string $pollingInterval = null;

    protected function getStats(): array
    {
        $liquidity = (float) Account::query()
            ->where('is_debt', false)
            ->sum('balance');

        $net = (float) Account::query()
            ->sum(DB::raw('CASE WHEN is_debt = 1 THEN -ABS(balance) ELSE balance END'));

        return [
            Stat::make('Liquidità', $this->formatCurrency($liquidity))
                ->icon('heroicon-m-wallet')
                ->description('Disponibilità immediata')
                ->descriptionColor('success')
                ->color('success'),
            Stat::make('Patrimonio', $this->formatCurrency($net))
                ->icon('heroicon-m-scale')
                ->description('Liquidità meno debiti')
                ->descriptionColor($net >= 0 ? 'success' : 'danger')
                ->color($net >= 0 ? 'success' : 'danger'),
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return Number::currency($amount, 'EUR', locale: 'it');
    }
}
