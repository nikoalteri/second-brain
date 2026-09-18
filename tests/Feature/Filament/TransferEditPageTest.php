<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountTransferService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransferEditPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_editing_a_transfer_from_the_panel_keeps_both_legs_and_balances_consistent(): void
    {
        Role::findOrCreate('superadmin');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('superadmin');

        $from = Account::factory()->create(['user_id' => $user->id, 'balance' => 100]);
        $to = Account::factory()->create(['user_id' => $user->id, 'balance' => 0]);

        $legs = app(AccountTransferService::class)->transfer($from, $to, 20, '2026-01-10');
        $out = $legs['out'];

        $this->actingAs($user);
        Filament::setCurrentPanel('admin');

        Livewire::test(EditTransaction::class, ['record' => $out->getRouteKey()])
            ->fillForm(['amount' => 30])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(70, $from->fresh()->balance);
        $this->assertEquals(30, $to->fresh()->balance);
        $this->assertEquals(30, Transaction::find($legs['in']->id)->amount);
    }
}
