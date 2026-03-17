<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use App\Models\Transaction;
use App\Observers\TransactionObserver;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);
        Gate::before(function ($user, $ability) {
            return $user->hasRole('superadmin');
        });

        Transaction::observe(TransactionObserver::class);

        // Registra il middleware rate limit API
        $this->app['router']->aliasMiddleware('api_rate_limit', \App\Http\Middleware\ApiRateLimitMiddleware::class);
    }
}
