<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\SetLocaleFromUserPreference;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\MenuItem;
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
            ->brandName('Fluxa')
            ->login()
            ->profile(EditProfile::class, isSimple: false)
            ->darkMode(false, true)
            ->defaultThemeMode(ThemeMode::Light)
            ->topNavigation()
            ->renderHook('panels::body.end', fn() => '<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>')
            ->colors([
                'primary' => Color::Amber,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label(__('Open frontend'))
                    ->url(url('/dashboard'))
                    ->icon('heroicon-o-arrow-top-right-on-square'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->resources([
                SubscriptionResource::class,
            ])
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigationGroups([
                NavigationGroup::make(__('Finance'))
                    ->items([
                        NavigationItem::make(__('Accounts'))
                            ->url('/admin/accounts')
                            ->icon('heroicon-o-banknotes'),
                        NavigationItem::make(__('Transactions'))
                            ->url('/admin/transactions')
                            ->icon('heroicon-o-arrow-path'),
                        NavigationItem::make(__('Subscriptions'))
                            ->url('/admin/subscriptions')
                            ->icon('heroicon-o-sparkles'),
                        NavigationItem::make(__('Loans'))
                            ->url('/admin/loans')
                            ->icon('heroicon-o-document-text'),
                        NavigationItem::make(__('Credit Cards'))
                            ->url('/admin/credit-cards')
                            ->icon('heroicon-o-credit-card'),
                        NavigationItem::make(__('Reports'))
                            ->url('/admin/finance-report')
                            ->icon('heroicon-o-chart-bar'),
                    ]),
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
                SetLocaleFromUserPreference::class,
            ], isPersistent: true);
    }
}
