<?php

namespace App\Filament\Widgets;

use App\Models\Subscription;
use App\Enums\SubscriptionStatus;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class SubscriptionWeightWidget extends ChartWidget
{
    protected int|string|array $columnSpan = 'half';

    public function getHeading(): ?string
    {
        return 'Annual Subscription Weight';
    }

    protected function getData(): array
    {
        $user = Auth::user();

        // Get active subscriptions for current user
        $subscriptions = Subscription::where('user_id', $user->id)
            ->where('status', SubscriptionStatus::ACTIVE)
            ->orderByDesc('annual_cost')
            ->get();

        if ($subscriptions->isEmpty()) {
            return [
                'labels' => ['No active subscriptions'],
                'datasets' => [
                    [
                        'data' => [1],
                        'backgroundColor' => ['#d1d5db'],
                    ],
                ],
            ];
        }

        $labels = $subscriptions->pluck('name')->toArray();
        $data = $subscriptions->pluck('annual_cost')->map(fn($cost) => (float) $cost)->toArray();

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => $data,
                    'backgroundColor' => [
                        '#dc2626',
                        '#ea580c',
                        '#f59e0b',
                        '#eab308',
                        '#84cc16',
                        '#22c55e',
                        '#10b981',
                        '#14b8a6',
                        '#06b6d4',
                        '#0284c7',
                        '#2563eb',
                        '#6366f1',
                    ],
                    'borderColor' => '#ffffff',
                    'borderWidth' => 2,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => true,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'padding' => 15,
                        'font' => [
                            'size' => 12,
                        ],
                    ],
                ],
            ],
        ];
    }
}
