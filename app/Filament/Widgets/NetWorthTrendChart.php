<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class NetWorthTrendChart extends ChartWidget
{
    public function getHeading(): ?string
    {
        return 'Net Worth Over Last 12 Months';
    }

    protected int|string|array $columnSpan = 'half';

    protected function getData(): array
    {
        $user = Auth::user();
        $months = [];
        $netWorths = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i)->startOfMonth();
            $months[] = $date->format('M Y');

            // Approximate: current balance from all active accounts
            $netWorth = Account::where('user_id', $user->id)
                ->whereNotIn('type', ['debt', 'credit_card'])
                ->sum('balance');

            $netWorths[] = (float) $netWorth;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Net Worth (€)',
                    'data' => $netWorths,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.4,
                    'fill' => true,
                    'pointBackgroundColor' => '#10b981',
                    'pointBorderColor' => '#fff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 4,
                ],
            ],
            'labels' => $months,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
