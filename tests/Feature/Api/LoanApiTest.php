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
}
