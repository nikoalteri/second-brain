<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\Loan;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_upcoming_payments_returns_impending_loan_installments(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'start_date' => now()->toDateString(),
            'withdrawal_day' => now()->day,
            'total_installments' => 2,
            'monthly_payment' => 250,
        ]);

        $payment = $loan->payments()->create([
            'due_date' => now()->addDays(3)->toDateString(),
            'amount' => 250,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/upcoming-payments?days=7');

        $response->assertOk()
            ->assertJsonPath('data.0.id', 'loan-' . $payment->id)
            ->assertJsonPath('data.0.type', 'loan')
            ->assertJsonPath('data.0.description', $loan->name)
            ->assertJsonPath('data.0.transaction_posted', false);
    }

    public function test_dashboard_upcoming_payments_defaults_to_three_days(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        CreditCardPayment::create([
            'credit_card_id' => $card->id,
            'due_date' => now()->addDays(2)->toDateString(),
            'installment_amount' => 148,
            'interest_amount' => 0,
            'principal_amount' => 148,
            'stamp_duty_amount' => 2,
            'total_amount' => 150,
            'status' => 'pending',
        ]);

        CreditCardPayment::create([
            'credit_card_id' => $card->id,
            'due_date' => now()->addDays(5)->toDateString(),
            'installment_amount' => 178,
            'interest_amount' => 0,
            'principal_amount' => 178,
            'stamp_duty_amount' => 2,
            'total_amount' => 180,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/upcoming-payments');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'credit-card');
    }

    public function test_dashboard_upcoming_payments_includes_subscription_reminders(): void
    {
        $frequency = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'subscription_frequency_id' => $frequency->id,
            'annual_cost' => 19.99,
            'monthly_cost' => 19.99,
            'next_renewal_date' => now()->addDays(2)->toDateString(),
            'auto_create_transaction' => true,
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/dashboard/upcoming-payments');

        $response->assertOk()
            ->assertJsonFragment([
                'id' => 'subscription-' . $subscription->id,
                'type' => 'subscription',
                'description' => $subscription->name,
                'payment_source_type' => 'account',
            ]);
    }
}
