<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\TransactionType;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransferWriteAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_panel_wraps_resource_pages_in_database_transactions(): void
    {
        $this->assertTrue(Filament::getPanel('admin')->hasDatabaseTransactions());
    }

    public function test_transfer_created_from_the_panel_is_rolled_back_when_the_paired_leg_fails(): void
    {
        Role::findOrCreate('superadmin');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('superadmin');

        $from = Account::factory()->create(['user_id' => $user->id, 'balance' => 100]);
        $to = Account::factory()->create(['user_id' => $user->id, 'balance' => 0]);
        $transfer = TransactionType::query()->firstOrCreate(['name' => 'Transfer'], ['is_income' => false]);

        // The IN leg is created in afterCreate(); make that write fail.
        Transaction::creating(function (Transaction $transaction) {
            if ($transaction->transfer_direction === 'in') {
                throw new RuntimeException('paired leg failed');
            }
        });

        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        try {
            Livewire::test(CreateTransaction::class)
                ->fillForm([
                    'account_id' => $from->id,
                    'transaction_type_id' => $transfer->id,
                    'to_account_id' => $to->id,
                    'amount' => 20,
                    'date' => '2026-01-10',
                    'description' => 'Move',
                ])
                ->call('create');
        } catch (RuntimeException) {
            // Expected: the failure propagates.
        }

        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
        $this->assertEquals(100, $from->fresh()->balance);
    }
}
