<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use App\Services\SubscriptionService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class MonthlySubscriptionCostWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $service = app(SubscriptionService::class);
        $userId = Auth::id();
        
        $totalMonthly = $service->getMonthlyTotal($userId);
        $activeCount = Subscription::where('user_id', $userId)
            ->active()
            ->count();

        return [
            Stat::make('Monthly Cost', '€' . number_format($totalMonthly, 2))
                ->description('All active subscriptions')
                ->icon('heroicon-o-arrow-path')
                ->color('info'),
            
            Stat::make('Active Subscriptions', $activeCount)
                ->description('Subscriptions')
                ->icon('heroicon-o-check-circle')
                ->color('success'),
        ];
    }
}
