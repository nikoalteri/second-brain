<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Models\Loan;
use App\Models\Subscription;
use App\Models\SubscriptionFrequency;
use App\Models\User;
use App\Services\UpcomingPaymentsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpcomingPaymentsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_pending_loan_payments_within_the_window(): void
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

        $this->actingAs($user);

        $service = app(UpcomingPaymentsService::class);
        $result = $service->forUser($user, 7);

        $this->assertCount(1, $result);
        $this->assertSame('loan-' . $payment->id, $result[0]['id']);
        $this->assertSame('loan', $result[0]['type']);
        $this->assertSame(250.0, $result[0]['amount']);
        $this->assertFalse($result[0]['transaction_posted']);
    }

    public function test_it_merges_and_sorts_all_three_payment_sources_by_due_date(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $frequency = SubscriptionFrequency::query()->where('slug', 'monthly')->firstOrFail();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'subscription_frequency_id' => $frequency->id,
            'annual_cost' => 19.99,
            'monthly_cost' => 19.99,
            'next_renewal_date' => now()->addDays(1)->toDateString(),
            'auto_create_transaction' => true,
        ]);

        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        $cardPayment = CreditCardPayment::create([
            'credit_card_id' => $card->id,
            'due_date' => now()->addDays(2)->toDateString(),
            'installment_amount' => 148,
            'interest_amount' => 0,
            'principal_amount' => 148,
            'stamp_duty_amount' => 2,
            'total_amount' => 150,
            'status' => 'pending',
        ]);

        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'start_date' => now()->toDateString(),
            'withdrawal_day' => now()->day,
            'total_installments' => 2,
            'monthly_payment' => 250,
        ]);

        $loanPayment = $loan->payments()->create([
            'due_date' => now()->addDays(3)->toDateString(),
            'amount' => 250,
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        $service = app(UpcomingPaymentsService::class);
        $result = $service->forUser($user, 7);

        $this->assertCount(3, $result);
        $this->assertSame('subscription-' . $subscription->id, $result[0]['id']);
        $this->assertSame('credit-card-' . $cardPayment->id, $result[1]['id']);
        $this->assertSame('loan-' . $loanPayment->id, $result[2]['id']);
    }

    public function test_it_excludes_other_users_payments(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $otherAccount = Account::factory()->create(['user_id' => $otherUser->id]);
        $otherLoan = Loan::factory()->create([
            'user_id' => $otherUser->id,
            'account_id' => $otherAccount->id,
            'start_date' => now()->toDateString(),
            'withdrawal_day' => now()->day,
            'total_installments' => 2,
            'monthly_payment' => 250,
        ]);

        $otherLoan->payments()->create([
            'due_date' => now()->addDays(3)->toDateString(),
            'amount' => 250,
            'status' => 'pending',
        ]);

        $this->actingAs($user);

        $service = app(UpcomingPaymentsService::class);
        $result = $service->forUser($user, 7);

        $this->assertCount(0, $result);
    }

    public function test_it_excludes_paid_payments(): void
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

        $loan->payments()->create([
            'due_date' => now()->addDays(3)->toDateString(),
            'amount' => 250,
            'status' => 'paid',
        ]);

        $this->actingAs($user);

        $service = app(UpcomingPaymentsService::class);
        $result = $service->forUser($user, 7);

        $this->assertCount(0, $result);
    }
}
