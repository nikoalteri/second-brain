<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\TransactionCategory;
use App\Models\Subscription;
use App\Models\CreditCard;
use App\Models\Loan;
use App\Models\User;
use App\Enums\SubscriptionStatus;
use App\Enums\SubscriptionFrequency;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class DummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first();
        if (!$user) {
            return;
        }

        // Accounts
        $checkingAccount = Account::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Checking Account'],
            [
                'type' => 'bank',
                'balance' => 5000,
                'currency' => 'EUR',
                'is_active' => true,
            ]
        );

        $savingsAccount = Account::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Savings Account'],
            [
                'type' => 'bank',
                'balance' => 15000,
                'currency' => 'EUR',
                'is_active' => true,
            ]
        );

        // Transaction Categories
        $groceries = TransactionCategory::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Groceries']
        );
        $restaurants = TransactionCategory::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Restaurants']
        );
        $utilities = TransactionCategory::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Utilities']
        );

        // Transactions - March 2026
        $expenseType = TransactionType::where('name', 'Expense')->first();
        $incomeType = TransactionType::where('name', 'Income')->first();

        if (!$expenseType || !$incomeType) {
            return;
        }

        // Sample expenses
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $checkingAccount->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $groceries->id,
            'date' => Carbon::create(2026, 3, 5),
            'amount' => -85.50,
            'description' => 'Carrefour',
            'competence_month' => '2026-03',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $checkingAccount->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $restaurants->id,
            'date' => Carbon::create(2026, 3, 7),
            'amount' => -120.00,
            'description' => 'Pizzeria Luigi',
            'competence_month' => '2026-03',
        ]);

        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $checkingAccount->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $utilities->id,
            'date' => Carbon::create(2026, 3, 10),
            'amount' => -150.00,
            'description' => 'Electric bill',
            'competence_month' => '2026-03',
        ]);

        // Sample income
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $checkingAccount->id,
            'transaction_type_id' => $incomeType->id,
            'date' => Carbon::create(2026, 3, 1),
            'amount' => 3500.00,
            'description' => 'Monthly salary',
            'competence_month' => '2026-03',
        ]);

        // More expenses in February for testing
        Transaction::create([
            'user_id' => $user->id,
            'account_id' => $checkingAccount->id,
            'transaction_type_id' => $expenseType->id,
            'transaction_category_id' => $groceries->id,
            'date' => Carbon::create(2026, 2, 15),
            'amount' => -95.00,
            'description' => 'Supermarket',
            'competence_month' => '2026-02',
        ]);

        // Generate more transaction history for realistic dashboard data
        $categories = [$groceries, $restaurants, $utilities];
        $descriptions = [
            'Carrefour', 'Auchan', 'Coop', 'Esselunga',
            'McDonald\'s', 'Pizzeria', 'Restaurant', 'Cafe',
            'Water bill', 'Gas bill', 'Internet bill', 'Phone bill',
        ];
        
        // Generate 35 more transactions across March and February
        for ($i = 0; $i < 35; $i++) {
            $days_ago = rand(1, 60);
            $date = Carbon::now()->subDays($days_ago);
            $category = $categories[array_rand($categories)];
            $description = $descriptions[array_rand($descriptions)];
            
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'transaction_type_id' => $expenseType->id,
                'transaction_category_id' => $category->id,
                'date' => $date,
                'amount' => -rand(10, 200),
                'description' => $description,
                'competence_month' => $date->format('Y-m'),
            ]);
        }

        // Add some income transactions
        for ($i = 0; $i < 3; $i++) {
            $date = Carbon::create(2026, 1 + $i, 1);
            Transaction::create([
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'transaction_type_id' => $incomeType->id,
                'date' => $date,
                'amount' => 3500.00,
                'description' => 'Monthly salary',
                'competence_month' => $date->format('Y-m'),
            ]);
        }

        // Subscriptions
        Subscription::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Netflix'],
            [
                'status' => SubscriptionStatus::ACTIVE,
                'frequency' => SubscriptionFrequency::MONTHLY,
                'monthly_cost' => 12.99,
                'next_renewal_date' => Carbon::now()->addDays(5),
            ]
        );

        Subscription::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Spotify'],
            [
                'status' => SubscriptionStatus::ACTIVE,
                'frequency' => SubscriptionFrequency::MONTHLY,
                'monthly_cost' => 10.99,
                'next_renewal_date' => Carbon::now()->addDays(10),
            ]
        );

        Subscription::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Microsoft 365'],
            [
                'status' => SubscriptionStatus::ACTIVE,
                'frequency' => SubscriptionFrequency::ANNUAL,
                'monthly_cost' => 9.99 / 12,
                'next_renewal_date' => Carbon::now()->addMonths(11),
            ]
        );

        // Credit Cards
        CreditCard::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Visa'],
            [
                'account_id' => $checkingAccount->id,
                'type' => 'revolving',
                'credit_limit' => 10000,
                'current_balance' => 2500,
                'interest_rate' => 14.5,
                'statement_day' => 15,
                'due_day' => 22,
                'interest_calculation_method' => 'daily_balance',
            ]
        );

        // Loans
        Loan::updateOrCreate(
            ['user_id' => $user->id, 'name' => 'Car Loan'],
            [
                'account_id' => $checkingAccount->id,
                'total_amount' => 30000,
                'monthly_payment' => 650,
                'withdrawal_day' => 1,
                'start_date' => Carbon::create(2025, 6, 1),
                'end_date' => Carbon::create(2032, 6, 1),
                'total_installments' => 84,
                'remaining_amount' => 22000,
            ]
        );

        $this->command->info('Dummy data seeded successfully!');
    }
}

