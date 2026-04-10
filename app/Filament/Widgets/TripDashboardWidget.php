<?php

namespace App\Filament\Widgets;

use App\Enums\TripStatus;
use App\Models\Activity;
use App\Models\Trip;
use App\Models\TripExpense;
use App\Models\TripParticipant;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class TripDashboardWidget extends BaseWidget
{
    protected int|string|array $columnSpan = 'full';

    /**
     * Get statistics for trip dashboard.
     *
     * Displays metrics including:
     * - Active trip count
     * - Total budget across active trips
     * - Scheduled activities count
     * - Total participants
     * - Total expenses
     *
     * @return array Array of Stat instances for dashboard display
     */
    protected function getStats(): array
    {
        $user = Auth::user();

        // Count active trips (in progress)
        $activeTrips = Trip::where('status', TripStatus::IN_PROGRESS)
            ->count();

        // Sum initial budget from active trips
        $totalBudget = Trip::withoutGlobalScopes()
            ->where('trips.user_id', $user->id)
            ->where('trips.status', TripStatus::IN_PROGRESS)
            ->join('trip_budgets', 'trips.id', '=', 'trip_budgets.trip_id')
            ->sum('trip_budgets.initial_amount');

        // Count activities in active trips' itineraries
        $scheduledActivities = Activity::whereHas('itinerary.trip', function ($query) {
            $query->where('status', TripStatus::IN_PROGRESS);
        })->count();

        // Count unique participants across all user's trips
        $totalParticipants = TripParticipant::whereHas('trip', function ($query) {
            // Trip has HasUserScoping, so it's already filtered
        })->distinct('email')->count();

        // Sum total expenses across all user's trips
        $totalExpenses = TripExpense::sum('amount');

        return [
            Stat::make('Active Trips', $activeTrips)
                ->description('Currently ongoing')
                ->icon('heroicon-o-calendar')
                ->color('primary'),

            Stat::make('Total Budget', '€' . number_format($totalBudget, 2, ',', '.'))
                ->description('Across active trips')
                ->icon('heroicon-o-banknote')
                ->color('info'),

            Stat::make('Scheduled Activities', $scheduledActivities)
                ->description('Across all trips')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('success'),

            Stat::make('Participants', $totalParticipants)
                ->description('Unique people')
                ->icon('heroicon-o-users')
                ->color('warning'),

            Stat::make('Total Expenses', '€' . number_format($totalExpenses, 2, ',', '.'))
                ->description('All trips combined')
                ->icon('heroicon-o-arrow-trending-up')
                ->color('danger'),
        ];
    }
}
