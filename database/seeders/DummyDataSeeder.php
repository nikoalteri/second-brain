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
        $user = User::where('email', 'test@example.com')->first();
        if (!$user) {
            return;
        }

        // Accounts
        $checkingAccount = Account::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Checking Account'],
            [
                'type' => 'checking',
                'balance' => 5000,
                'currency' => 'EUR',
                'is_active' => true,
            ]
        );

        $savingsAccount = Account::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Savings Account'],
            [
                'type' => 'savings',
                'balance' => 15000,
                'currency' => 'EUR',
                'is_active' => true,
            ]
        );

        // Transaction Categories
        $groceries = TransactionCategory::firstOrCreate(
            ['name' => 'Groceries'],
            ['description' => 'Food and groceries']
        );

        $restaurants = TransactionCategory::firstOrCreate(
            ['name' => 'Restaurants'],
            ['description' => 'Dining out'],
            ['parent_id' => null]
        );

        $utilities = TransactionCategory::firstOrCreate(
            ['name' => 'Utilities'],
            ['description' => 'Electricity, water, internet']
        );

        // Transactions - March 2026
        $expenseType = TransactionType::where('name', 'Expense')->first();
        $incomeType = TransactionType::where('name', 'Income')->first();

        // Sample expenses
        Transaction::firstOrCreate(
            [
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'date' => Carbon::create(2026, 3, 5),
                'amount' => -85.50,
            ],
            [
                'transaction_type_id' => $expenseType->id,
                'transaction_category_id' => $groceries->id,
                'description' => 'Carrefour',
                'competence_month' => '2026-03',
            ]
        );

        Transaction::firstOrCreate(
            [
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'date' => Carbon::create(2026, 3, 7),
                'amount' => -120.00,
            ],
            [
                'transaction_type_id' => $expenseType->id,
                'transaction_category_id' => $restaurants->id,
                'description' => 'Pizzeria Luigi',
                'competence_month' => '2026-03',
            ]
        );

        Transaction::firstOrCreate(
            [
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'date' => Carbon::create(2026, 3, 10),
                'amount' => -150.00,
            ],
            [
                'transaction_type_id' => $expenseType->id,
                'transaction_category_id' => $utilities->id,
                'description' => 'Electric bill',
                'competence_month' => '2026-03',
            ]
        );

        // Sample income
        Transaction::firstOrCreate(
            [
                'user_id' => $user->id,
                'account_id' => $checkingAccount->id,
                'date' => Carbon::create(2026, 3, 1),
                'amount' => 3500.00,
            ],
            [
                'transaction_type_id' => $incomeType->id,
                'description' => 'Monthly salary',
                'competence_month' => '2026-03',
            ]
        );

        // Subscriptions
        Subscription::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Netflix'],
            [
                'status' => SubscriptionStatus::ACTIVE,
                'frequency' => SubscriptionFrequency::MONTHLY,
                'monthly_cost' => 12.99,
                'start_date' => Carbon::create(2026, 1, 1),
                'next_renewal_date' => Carbon::now()->addDays(5),
            ]
        );

        Subscription::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Spotify'],
            [
                'status' => SubscriptionStatus::ACTIVE,
                'frequency' => SubscriptionFrequency::MONTHLY,
                'monthly_cost' => 10.99,
                'start_date' => Carbon::create(2026, 1, 15),
                'next_renewal_date' => Carbon::now()->addDays(10),
            ]
        );

        Subscription::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Microsoft 365'],
            [
                'status' => SubscriptionStatus::ACTIVE,
                'frequency' => SubscriptionFrequency::ANNUAL,
                'monthly_cost' => 9.99 / 12,
                'start_date' => Carbon::create(2026, 2, 1),
                'next_renewal_date' => Carbon::now()->addMonths(11),
            ]
        );

        // Credit Cards
        CreditCard::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Visa'],
            [
                'account_id' => $checkingAccount->id,
                'card_number' => '4532XXXXXXXX1234',
                'cardholder_name' => $user->name,
                'credit_limit' => 10000,
                'current_balance' => 2500,
                'annual_percentage_rate' => 14.5,
                'billing_cycle_day' => 15,
                'interest_calculation_method' => 'daily_balance',
            ]
        );

        // Loans
        Loan::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'Car Loan'],
            [
                'principal_amount' => 30000,
                'remaining_amount' => 22000,
                'annual_interest_rate' => 5.5,
                'monthly_payment' => 650,
                'start_date' => Carbon::create(2025, 6, 1),
                'end_date' => Carbon::create(2032, 6, 1),
                'status' => 'active',
            ]
        );

        $this->command->info('Dummy data seeded successfully!');
    }
}
