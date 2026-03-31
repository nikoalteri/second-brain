<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use App\Filament\Widgets\AccountsListWidget;
use App\Filament\Widgets\CreditCardsKpiOverview;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\CashflowReport;
use App\Filament\Widgets\SubscriptionsStatsWidget;
use App\Filament\Widgets\NetWorthWidget;
use App\Filament\Widgets\TotalDebtsWidget;
use App\Filament\Widgets\UpcomingPaymentsWidget;
use App\Filament\Widgets\ExpensesByCategoryChart;
use App\Filament\Widgets\NetWorthTrendChart;
use App\Filament\Widgets\MonthlyCashflowChart;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;


class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->topNavigation()
            ->renderHook('panels::body.end', fn() => '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->resources([
                SubscriptionResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
                FinanceReport::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // AccountWidget::class,
                // FilamentInfoWidget::class,
                NetWorthWidget::class,
                TotalDebtsWidget::class,
                SubscriptionsStatsWidget::class,
                ExpensesByCategoryChart::class,
                NetWorthTrendChart::class,
                MonthlyCashflowChart::class,
                UpcomingPaymentsWidget::class,
                StatsOverview::class,
                CreditCardsKpiOverview::class,
                AccountsListWidget::class,
                CashflowReport::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                'module:adminpanel',
            ]);
    }
}
