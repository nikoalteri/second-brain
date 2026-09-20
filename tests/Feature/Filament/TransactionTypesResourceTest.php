<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\TransactionTypes\TransactionTypeResource;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TransactionTypesResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_transaction_types_are_locked_down_against_create_edit_and_delete(): void
    {
        $type = TransactionType::query()->create(['name' => 'Expenses', 'is_income' => false]);

        $this->assertFalse(TransactionTypeResource::canCreate());
        $this->assertFalse(TransactionTypeResource::canEdit($type));
        $this->assertFalse(TransactionTypeResource::canDelete($type));
        $this->assertFalse(TransactionTypeResource::canDeleteAny());
    }

    public function test_transaction_types_list_still_renders_for_superadmin(): void
    {
        Role::findOrCreate('superadmin');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('superadmin');

        TransactionType::query()->create(['name' => 'Cashback', 'is_income' => true]);

        $response = $this->actingAs($this->withPanelMfa($admin))->get('/hub/transaction-types');

        $response->assertOk()->assertSee('Cashback');
    }

    public function test_transaction_type_create_route_no_longer_exists(): void
    {
        Role::findOrCreate('superadmin');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('superadmin');

        $this->actingAs($this->withPanelMfa($admin))->get('/hub/transaction-types/create')->assertNotFound();
    }
}
