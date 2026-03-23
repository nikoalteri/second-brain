<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Models\CreditCardExpense;
use App\Models\CreditCardPayment;
use App\Models\LoanPayment;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Observers\CreditCardCycleObserver;
use App\Observers\CreditCardExpenseObserver;
use App\Observers\CreditCardPaymentObserver;
use App\Observers\LoanPaymentObserver;
use App\Observers\SubscriptionObserver;
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

        Transaction::observe(TransactionObserver::class);
        LoanPayment::observe(LoanPaymentObserver::class);
        CreditCardCycle::observe(CreditCardCycleObserver::class);
        CreditCardPayment::observe(CreditCardPaymentObserver::class);
        CreditCardExpense::observe(CreditCardExpenseObserver::class);
        Subscription::observe(SubscriptionObserver::class);

        // Registra il middleware rate limit API
        $this->app['router']->aliasMiddleware('api_rate_limit', \App\Http\Middleware\ApiRateLimitMiddleware::class);
    }
}
