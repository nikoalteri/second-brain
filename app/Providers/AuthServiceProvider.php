<?php

namespace App\Providers;

use App\Models\Account;
use App\Models\Loan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use App\Policies\AccountPolicy;
use App\Policies\LoanPolicy;
use App\Policies\TransactionCategoryPolicy;
use App\Policies\TransactionPolicy;
use App\Policies\TransactionTypePolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     */
    protected $policies = [
        User::class => UserPolicy::class,
        Account::class => AccountPolicy::class,
        Loan::class => LoanPolicy::class,
        Transaction::class => TransactionPolicy::class,
        TransactionCategory::class => TransactionCategoryPolicy::class,
        TransactionType::class => TransactionTypePolicy::class,
        // Subscription::class => SubscriptionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Superadmin bypassa tutte le policy
        Gate::before(function (User $user, string $ability) {
            return $user->hasRole('superadmin') ? true : null;
        });

        $this->registerPolicies();

        // Gates custom (opzionale)
        Gate::define('view-finance-module', function (User $user) {
            return $user->hasPermissionTo('finance.accounts.viewAny');
        });
    }
}
