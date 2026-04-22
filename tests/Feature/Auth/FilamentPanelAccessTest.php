<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FilamentPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_viewer_cannot_access_the_admin_panel(): void
    {
        Permission::create(['name' => 'module.finance']);
        $viewerRole = Role::create(['name' => 'viewer']);
        $viewerRole->givePermissionTo('module.finance');

        $viewer = User::factory()->create([
            'is_active' => true,
        ]);
        $viewer->assignRole('viewer');

        $this->assertFalse($viewer->canAccessPanel($this->createMock(Panel::class)));
    }

    public function test_admin_can_access_the_admin_panel_with_permission(): void
    {
        Permission::create(['name' => 'module.adminpanel']);
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo('module.adminpanel');

        $admin = User::factory()->create([
            'is_active' => true,
        ]);
        $admin->assignRole('admin');

        $this->assertTrue($admin->canAccessPanel($this->createMock(Panel::class)));
    }

    public function test_superadmin_can_access_the_admin_panel_without_explicit_permission(): void
    {
        Role::create(['name' => 'superadmin']);

        $superadmin = User::factory()->create([
            'is_active' => true,
        ]);
        $superadmin->assignRole('superadmin');

        $this->assertTrue($superadmin->canAccessPanel($this->createMock(Panel::class)));
    }

    public function test_inactive_users_cannot_access_the_admin_panel(): void
    {
        Permission::create(['name' => 'module.adminpanel']);
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo('module.adminpanel');

        $admin = User::factory()->create([
            'is_active' => false,
        ]);
        $admin->assignRole('admin');

        $this->assertFalse($admin->canAccessPanel($this->createMock(Panel::class)));
    }
}
