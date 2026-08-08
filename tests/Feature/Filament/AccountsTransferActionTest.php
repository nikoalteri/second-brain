<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AccountsTransferActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounts_list_renders_with_the_transfer_action(): void
    {
        Role::findOrCreate('superadmin');

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('superadmin');
        Account::factory()->count(2)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/hub/accounts');

        $response->assertOk()->assertSee('Transfer money');
    }
}
