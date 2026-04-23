<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Loan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected null|string $pollingInterval = null;

    protected function getStats(): array
    {
        $userId = Auth::id();

        $liquidity = (float) Account::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->sum('balance');

        $activeLoansTotal = (float) Loan::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->sum('remaining_amount');

        $net = $liquidity - $activeLoansTotal;

        return [
            Stat::make('Liquidity', $this->formatCurrency($liquidity))
                ->icon('heroicon-m-wallet')
                ->description('Immediate availability')
                ->descriptionColor('success')
                ->color('success'),
            Stat::make('Net Worth', $this->formatCurrency($net))
                ->icon('heroicon-m-scale')
                ->description('Liquidity minus active loans')
                ->descriptionColor($net >= 0 ? 'success' : 'danger')
                ->color($net >= 0 ? 'success' : 'danger'),
        ];
    }

    private function formatCurrency(float $amount): string
    {
        return Number::currency($amount, 'EUR');
    }
}
