<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\Account;
use App\Models\Notification;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelScopingTest extends TestCase
{
    use RefreshDatabase;

    private function panelUser(): User
    {
        Permission::findOrCreate('module.adminpanel', 'web');
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('module.adminpanel');

        return $user->fresh();
    }

    public function test_panel_user_cannot_open_another_users_account_record(): void
    {
        $userA = $this->panelUser();
        $userB = User::factory()->create(['is_active' => true]);

        $ownAccount = Account::factory()->create(['user_id' => $userA->id]);
        $foreignAccount = Account::factory()->create(['user_id' => $userB->id]);

        $this->actingAs($userA)->get("/hub/accounts/{$ownAccount->id}/edit")->assertOk();
        $this->actingAs($userA)->get("/hub/accounts/{$foreignAccount->id}/edit")->assertNotFound();
    }

    public function test_panel_user_cannot_open_another_users_notification_record(): void
    {
        $userA = $this->panelUser();
        $userB = User::factory()->create(['is_active' => true]);

        $ownNotification = Notification::withoutGlobalScopes()->create([
            'user_id' => $userA->id, 'type' => 'in_app', 'title' => 'T own', 'message' => 'M own',
        ]);
        $foreignNotification = Notification::withoutGlobalScopes()->create([
            'user_id' => $userB->id, 'type' => 'in_app', 'title' => 'T foreign', 'message' => 'M foreign',
        ]);

        $this->actingAs($userA)->get("/hub/notifications/{$ownNotification->id}/edit")->assertOk();
        $this->actingAs($userA)->get("/hub/notifications/{$foreignNotification->id}/edit")->assertNotFound();
    }

    public function test_panel_user_cannot_open_another_users_user_setting_record(): void
    {
        $userA = $this->panelUser();
        $userB = User::factory()->create(['is_active' => true]);

        $ownSetting = UserSetting::withoutGlobalScopes()->create([
            'user_id' => $userA->id, 'setting_key' => 'theme', 'setting_value' => 'dark',
        ]);
        $foreignSetting = UserSetting::withoutGlobalScopes()->create([
            'user_id' => $userB->id, 'setting_key' => 'theme', 'setting_value' => 'light',
        ]);

        $this->actingAs($userA)->get("/hub/user-settings/{$ownSetting->id}/edit")->assertOk();
        $this->actingAs($userA)->get("/hub/user-settings/{$foreignSetting->id}/edit")->assertNotFound();
    }

    public function test_superadmin_can_open_another_users_account_record(): void
    {
        Role::create(['name' => 'superadmin']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('superadmin');

        $foreignAccount = Account::factory()->create(['user_id' => User::factory()->create()->id]);

        $this->actingAs($admin)->get("/hub/accounts/{$foreignAccount->id}/edit")->assertOk();
    }
}
