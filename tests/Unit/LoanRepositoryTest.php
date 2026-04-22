<?php

namespace Tests\Unit;

use App\Models\Account;
use App\Models\Loan;
use App\Models\User;
use App\Repositories\LoanRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoanRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_create_and_find_a_loan()
    {
        $repo = new LoanRepository();
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);

        $loan = $repo->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'name' => 'Test Loan',
            'total_amount' => 1000,
            'monthly_payment' => 100,
            'withdrawal_day' => 15,
            'skip_weekends' => false,
            'start_date' => now(),
            'total_installments' => 10,
            'remaining_amount' => 1000,
            'status' => 'active',
        ]);

        $found = $repo->find($loan->id);
        $this->assertNotNull($found);
        $this->assertEquals('Test Loan', $found->name);
    }

    #[Test]
    public function it_can_update_a_loan()
    {
        $repo = new LoanRepository();
        $loan = Loan::factory()->create(['name' => 'Old Name']);
        $repo->update($loan, ['name' => 'New Name']);
        $loan->refresh();
        $this->assertEquals('New Name', $loan->name);
    }

    #[Test]
    public function it_can_delete_a_loan()
    {
        $repo = new LoanRepository();
        $loan = Loan::factory()->create();
        $repo->delete($loan);
        $this->assertNull($repo->find($loan->id));
    }
}
