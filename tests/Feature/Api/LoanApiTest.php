<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\Loan;
use App\Services\LoanScheduleService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_own_loans(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create an account for each user (without auth)
        $accountA = Account::factory()->create(['user_id' => $userA->id]);
        $accountB = Account::factory()->create(['user_id' => $userB->id]);

        // Create loans without auth so HasUserScoping doesn't override user_id
        Loan::factory()->count(3)->create(['user_id' => $userA->id, 'account_id' => $accountA->id]);
        Loan::factory()->count(2)->create(['user_id' => $userB->id, 'account_id' => $accountB->id]);

        Sanctum::actingAs($userA);

        $response = $this->getJson('/api/v1/loans');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_loan_show_includes_payments(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $loan    = Loan::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/loans/{$loan->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'payments']]);
    }

    public function test_user_cannot_access_another_users_loan(): void
    {
        $userA   = User::factory()->create();
        $userB   = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $userA->id]);
        $loan    = Loan::factory()->create(['user_id' => $userA->id, 'account_id' => $account->id]);

        Sanctum::actingAs($userB);

        $response = $this->getJson("/api/v1/loans/{$loan->id}");

        // HasUserScoping filters loan out for userB → 404
        $response->assertStatus(404);
    }

    public function test_loans_can_be_filtered_by_status(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Loan::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'active']);
        Loan::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'active']);
        Loan::factory()->create(['user_id' => $user->id, 'account_id' => $account->id, 'status' => 'completed']);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/loans?filter[status]=active');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        foreach ($response->json('data') as $loan) {
            $this->assertEquals('active', $loan['status']);
        }
    }

    public function test_user_can_create_loan(): void
    {
        $user    = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/loans', [
            'name'               => 'Home Loan',
            'account_id'         => $account->id,
            'total_amount'       => 100000.00,
            'monthly_payment'    => 500.00,
            'interest_rate'      => 3.5,
            'withdrawal_day'     => 5,
            'start_date'         => '2024-01-01',
            'total_installments' => 240,
            'paid_installments'  => 0,
            'remaining_amount'   => 100000.00,
            'status'             => 'active',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'total_amount', 'status']])
            ->assertJsonPath('data.name', 'Home Loan')
            ->assertJsonPath('data.status', 'active');

        $this->assertDatabaseHas('loans', [
            'user_id' => $user->id,
            'name'    => 'Home Loan',
        ]);
    }

    public function test_creating_loan_auto_generates_schedule(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/loans', [
            'name' => 'Car Loan',
            'account_id' => $account->id,
            'total_amount' => 1200.00,
            'monthly_payment' => 400.00,
            'interest_rate' => 0,
            'withdrawal_day' => 5,
            'start_date' => '2026-01-01',
            'total_installments' => 3,
            'paid_installments' => 0,
            'remaining_amount' => 1200.00,
            'status' => 'active',
        ]);

        $response->assertCreated();

        $loanId = $response->json('data.id');

        $this->assertCount(3, Loan::findOrFail($loanId)->payments);
    }

    public function test_loan_show_returns_generated_schedule_fields(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'start_date' => '2026-01-01',
            'withdrawal_day' => 5,
            'total_installments' => 2,
            'monthly_payment' => 250,
        ]);

        app(LoanScheduleService::class)->generate($loan);

        $payment = $loan->fresh()->payments()->first();
        $payment->update([
            'actual_date' => '2026-01-05',
            'notes' => 'Paid manually',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/loans/{$loan->id}");

        $response->assertOk()
            ->assertJsonPath('data.payments.0.actual_date', '2026-01-05')
            ->assertJsonPath('data.payments.0.notes', 'Paid manually');
    }

    public function test_user_can_generate_loan_schedule_via_api(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $loan = Loan::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'start_date' => '2026-01-01',
            'withdrawal_day' => 5,
            'total_installments' => 3,
            'monthly_payment' => 250,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/loans/{$loan->id}/generate-schedule");

        $response->assertOk()
            ->assertJsonCount(3, 'data.payments');

        $this->assertCount(3, $loan->fresh()->payments);
    }

    // ─── marking an installment as paid ──────────────────────────────────────────────────────

    private function loanWithInstallment(User $user, string $due, float $balance = 1000.0): array
    {
        $account = Account::factory()->create(['user_id' => $user->id, 'balance' => $balance]);
        $loan = Loan::factory()->create([
            'user_id' => $user->id, 'account_id' => $account->id, 'start_date' => '2026-01-01',
            'withdrawal_day' => 1, 'total_installments' => 4, 'monthly_payment' => 100, 'total_amount' => 400,
        ]);
        $payment = $loan->payments()->create(['due_date' => $due, 'amount' => 100, 'status' => 'pending']);

        return [$loan, $payment, $account];
    }

    public function test_an_overdue_installment_can_be_marked_paid_without_posting_it_twice(): void
    {
        $user = User::factory()->create();
        // Due in the past: the account movement already exists, only the status is still pending.
        [$loan, $payment, $account] = $this->loanWithInstallment($user, now()->subDays(3)->toDateString());
        $this->assertSame(1, \App\Models\Transaction::withoutGlobalScopes()->where('loan_payment_id', $payment->id)->count());
        $balanceBefore = (float) $account->fresh()->balance;
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/loans/{$loan->id}/payments/{$payment->id}/mark-paid")->assertOk();

        $paid = collect($response->json('data.payments'))->firstWhere('id', $payment->id);
        $this->assertSame('paid', $paid['status']);
        $this->assertSame(now()->toDateString(), $paid['actual_date']);
        $response->assertJsonPath('data.paid_installments', 1);
        $this->assertSame(1, \App\Models\Transaction::withoutGlobalScopes()->where('loan_payment_id', $payment->id)->count(), 'still one movement');
        $this->assertEquals($balanceBefore, (float) $account->fresh()->balance, 'the balance had already moved when it fell due');
    }

    public function test_a_future_installment_can_be_paid_early_and_is_posted_once(): void
    {
        $user = User::factory()->create();
        [$loan, $payment, $account] = $this->loanWithInstallment($user, now()->addDays(20)->toDateString());
        $this->assertSame(0, \App\Models\Transaction::withoutGlobalScopes()->where('loan_payment_id', $payment->id)->count());
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/loans/{$loan->id}/payments/{$payment->id}/mark-paid", ['actual_date' => now()->subDay()->toDateString()])->assertOk();

        $this->assertSame(1, \App\Models\Transaction::withoutGlobalScopes()->where('loan_payment_id', $payment->id)->count());
        $this->assertEquals(900.0, (float) $account->fresh()->balance);
        $this->assertSame(now()->subDay()->toDateString(), $payment->fresh()->actual_date->toDateString());
    }

    public function test_marking_an_installment_paid_updates_the_loan_progress_and_is_idempotent(): void
    {
        $user = User::factory()->create();
        [$loan, $payment, $account] = $this->loanWithInstallment($user, now()->subDay()->toDateString());
        $loan->payments()->create(['due_date' => now()->addMonth()->toDateString(), 'amount' => 100, 'status' => 'pending']);
        Sanctum::actingAs($user);
        $remainingBefore = (float) $loan->fresh()->remaining_amount;

        $this->postJson("/api/v1/loans/{$loan->id}/payments/{$payment->id}/mark-paid")->assertOk()->assertJsonPath('data.paid_installments', 1);
        $firstDate = $payment->fresh()->actual_date->toDateString();
        $this->assertLessThan($remainingBefore, (float) $loan->fresh()->remaining_amount);

        // A second call changes nothing, even with another date.
        $this->postJson("/api/v1/loans/{$loan->id}/payments/{$payment->id}/mark-paid", ['actual_date' => now()->subDays(10)->toDateString()])->assertOk()->assertJsonPath('data.paid_installments', 1);
        $this->assertSame($firstDate, $payment->fresh()->actual_date->toDateString());
        $this->assertSame(1, \App\Models\Transaction::withoutGlobalScopes()->where('loan_payment_id', $payment->id)->count());
    }

    public function test_a_future_payment_date_is_rejected(): void
    {
        $user = User::factory()->create();
        [$loan, $payment] = $this->loanWithInstallment($user, now()->subDay()->toDateString());
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/loans/{$loan->id}/payments/{$payment->id}/mark-paid", ['actual_date' => now()->addDay()->toDateString()])->assertUnprocessable();
        $this->assertSame('pending', $payment->fresh()->status->value);
    }

    public function test_only_the_owner_can_mark_an_installment_paid_and_it_must_belong_to_the_loan(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        [$loan, $payment] = $this->loanWithInstallment($owner, now()->subDay()->toDateString());
        [$otherLoan, $otherPayment] = $this->loanWithInstallment($owner, now()->subDay()->toDateString());

        $this->postJson("/api/v1/loans/{$loan->id}/payments/{$payment->id}/mark-paid")->assertUnauthorized();

        // Loans are scoped to their owner, so a stranger does not even learn that it exists.
        Sanctum::actingAs($stranger);
        $this->postJson("/api/v1/loans/{$loan->id}/payments/{$payment->id}/mark-paid")->assertNotFound();

        Sanctum::actingAs($owner);
        $this->postJson("/api/v1/loans/{$loan->id}/payments/{$otherPayment->id}/mark-paid")->assertNotFound();
        $this->assertSame('pending', $payment->fresh()->status->value);
        $this->assertSame('pending', $otherPayment->fresh()->status->value);
    }
}
