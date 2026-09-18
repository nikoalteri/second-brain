<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
use App\Observers\CreditCardObserver;
use App\Observers\CreditCardPaymentObserver;
use App\Observers\LoanPaymentObserver;
use App\Observers\SubscriptionObserver;
use App\Observers\TransactionObserver;
use App\Services\Chatbot\IntentRouter;
use App\Services\Chatbot\Intents\AccountBalancesIntent;
use App\Services\Chatbot\Intents\MonthlySpendingIntent;
use App\Services\Chatbot\Intents\UpcomingPaymentsIntent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(IntentRouter::class, fn ($app) => new IntentRouter([
            $app->make(AccountBalancesIntent::class),
            $app->make(UpcomingPaymentsIntent::class),
            $app->make(MonthlySpendingIntent::class),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api-read', function (Request $request) {
            return Limit::perMinute(100)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('api-write', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Guest auth endpoints. The per-account key stops guessing against one account; the
        // per-IP cap stays generous because behind an untrusted proxy every client shares one IP.
        foreach (['auth-login', 'auth-password'] as $name) {
            RateLimiter::for($name, function (Request $request) use ($name) {
                $account = mb_strtolower((string) $request->input('email'));

                return [
                    Limit::perMinute(5)->by($name.'|'.$account.'|'.$request->ip()),
                    Limit::perMinute(60)->by($name.'-ip|'.$request->ip()),
                ];
            });
        }

        // The user is resolved through the sanctum guard because this runs before the
        // GraphQL authentication middleware; guests share a per-IP budget.
        RateLimiter::for('graphql', function (Request $request) {
            $user = $request->user('sanctum');

            return $user
                ? Limit::perMinute((int) config('lighthouse.request_limits.per_minute_authenticated'))->by('graphql-user|'.$user->id)
                : Limit::perMinute((int) config('lighthouse.request_limits.per_minute_guest'))->by('graphql-ip|'.$request->ip());
        });

        RateLimiter::for('auth-register', function (Request $request) {
            return Limit::perMinute(5)->by('auth-register|'.$request->ip());
        });

        ResetPassword::createUrlUsing(function (object $user, string $token): string {
            return rtrim((string) config('app.url'), '/').'/reset-password?token='.$token.'&email='.urlencode($user->getEmailForPasswordReset());
        });

        Vite::prefetch(concurrency: 3);

        // Balance observers and paired transfer legs write after the main statement:
        // run every Filament action (including delete and bulk actions) in one transaction.
        \Filament\Actions\Action::configureUsing(fn (\Filament\Actions\Action $action) => $action->databaseTransaction());

        Transaction::observe(TransactionObserver::class);
        LoanPayment::observe(LoanPaymentObserver::class);
        CreditCard::observe(CreditCardObserver::class);
        CreditCardCycle::observe(CreditCardCycleObserver::class);
        CreditCardPayment::observe(CreditCardPaymentObserver::class);
        CreditCardExpense::observe(CreditCardExpenseObserver::class);
        Subscription::observe(SubscriptionObserver::class);
    }
}
