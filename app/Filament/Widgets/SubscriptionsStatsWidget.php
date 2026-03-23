<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use App\Enums\SubscriptionStatus;
use App\Services\SubscriptionService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class SubscriptionsStatsWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'half';

    protected function getStats(): array
    {
        $user = Auth::user();
        $service = app(SubscriptionService::class);

        // Get active subscriptions
        $activeSubscriptions = Subscription::where('user_id', $user->id)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->get();

        // Calculate totals
        $totalAnnualCost = $activeSubscriptions->sum('annual_cost');
        $totalMonthlyCost = $service->getMonthlyTotal($user->id);
        $activeCount = $activeSubscriptions->count();

        return [
            Stat::make('Annual Subscriptions Cost', '€' . number_format($totalAnnualCost, 2, ',', '.'))
                ->description($activeCount . ' active subscriptions')
                ->color('primary'),

            Stat::make('Monthly Subscriptions Cost', '€' . number_format($totalMonthlyCost, 2, ',', '.'))
                ->description('Recurring monthly expense')
                ->color('success'),
        ];
    }
}
