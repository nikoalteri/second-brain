<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransactionDeleteAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_filament_actions_use_database_transactions_by_default(): void
    {
        $this->assertTrue(DeleteAction::make()->hasDatabaseTransactions());
        $this->assertTrue(DeleteBulkAction::make()->hasDatabaseTransactions());
    }

    public function test_delete_action_is_rolled_back_when_a_later_step_fails(): void
    {
        Role::findOrCreate('superadmin');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('superadmin');

        $account = Account::factory()->create(['user_id' => $user->id, 'balance' => 0]);
        $type = TransactionType::query()->firstOrCreate(['name' => 'Expenses'], ['is_income' => false]);
        $transaction = Transaction::factory()->create([
            'user_id' => $user->id,
            'account_id' => $account->id,
            'transaction_type_id' => $type->id,
            'amount' => -30,
        ]);
        $balanceBefore = (float) $account->fresh()->balance;

        // Registered after the app observer, so the balance has already been restored when it fails.
        Transaction::deleted(function () {
            throw new RuntimeException('failure after observer');
        });

        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        try {
            Livewire::test(ListTransactions::class)->callTableAction('delete', $transaction);
        } catch (RuntimeException) {
            // Expected: the failure propagates.
        }

        $this->assertNull($transaction->fresh()->deleted_at);
        $this->assertEquals($balanceBefore, (float) $account->fresh()->balance);
    }
}
