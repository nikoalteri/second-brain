<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
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
use App\Filament\Resources\Subscriptions\SubscriptionResource;
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
            ->path('hub')
            ->brandName('Fluxa')
            ->login()
            ->darkMode(false, true)
            ->defaultThemeMode(ThemeMode::Light)
            ->topNavigation()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->userMenuItems([
                MenuItem::make()
                    ->label('Open frontend')
                    ->url(url('/home'))
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
                NavigationGroup::make('Finance')
                    ->items([
                        NavigationItem::make('Accounts')
                            ->url('/hub/accounts')
                            ->icon('heroicon-o-banknotes'),
                        NavigationItem::make('Transactions')
                            ->url('/hub/transactions')
                            ->icon('heroicon-o-arrow-path'),
                        NavigationItem::make('Subscriptions')
                            ->url('/hub/subscriptions')
                            ->icon('heroicon-o-sparkles'),
                        NavigationItem::make('Loans')
                            ->url('/hub/loans')
                            ->icon('heroicon-o-document-text'),
                        NavigationItem::make('Credit Cards')
                            ->url('/hub/credit-cards')
                            ->icon('heroicon-o-credit-card'),
                        NavigationItem::make('Saving Goals')
                            ->url('/hub/saving-goals')
                            ->icon('heroicon-o-flag'),
                        NavigationItem::make('Reports')
                            ->url('/hub/finance-report')
                            ->icon('heroicon-o-chart-bar'),
                    ]),
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
            ]);
    }
}
