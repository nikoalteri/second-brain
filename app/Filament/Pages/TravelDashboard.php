<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\TripDashboardWidget;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class TravelDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;
    protected static string $routePath = 'travel-dashboard';
    protected static string|\UnitEnum|null $navigationGroup = 'Travel';
    protected static ?int $navigationSort = 10;

    public function getTitle(): string
    {
        return 'Travel Dashboard';
    }

    /**
     * Get the header widgets for this dashboard.
     *
     * @return array Array of widget classes to display
     */
    protected function getHeaderWidgets(): array
    {
        return [
            TripDashboardWidget::class,
        ];
    }

    /**
     * Get the footer widgets for this dashboard.
     *
     * @return array Array of widget classes for footer section
     */
    protected function getFooterWidgets(): array
    {
        return [];
    }
}
