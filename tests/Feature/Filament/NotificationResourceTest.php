<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\Notifications\Pages\ListNotifications;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NotificationResourceTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, string $title, array $attrs = []): Notification
    {
        return Notification::query()->withoutUserScope()->create($attrs + [
            'user_id' => $user->id, 'type' => 'in_app', 'title' => $title, 'message' => "{$title} message", 'severity' => 'warning',
        ]);
    }

    private function admin(): User
    {
        Permission::findOrCreate('module.adminpanel', 'web');
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('module.adminpanel');

        return $this->withPanelMfa($user);
    }

    private function superadmin(): User
    {
        Role::findOrCreate('superadmin');
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('superadmin');

        return $this->withPanelMfa($user);
    }

    public function test_list_is_reachable_and_isolated_for_ordinary_admins(): void
    {
        $admin = $this->admin();
        $other = User::factory()->create();
        $this->notify($admin, 'Own alert');
        $this->notify($other, 'Foreign alert');

        $this->actingAs($admin)->get('/hub/notifications')
            ->assertOk()->assertSee('Own alert')->assertDontSee('Foreign alert');
    }

    public function test_superadmin_sees_all_but_can_only_mark_own_read(): void
    {
        $super = $this->superadmin();
        $other = User::factory()->create();
        $own = $this->notify($super, 'Own alert');
        $foreign = $this->notify($other, 'Foreign alert');

        $this->actingAs($super);

        Livewire::test(ListNotifications::class)
            ->assertCanSeeTableRecords([$own, $foreign])
            ->assertTableActionVisible('markRead', $own)
            ->assertTableActionHidden('markRead', $foreign)
            ->callTableBulkAction('markRead', [$own, $foreign]);

        $this->assertNotNull($own->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_mark_read_action_marks_the_record_read(): void
    {
        $admin = $this->admin();
        $own = $this->notify($admin, 'Own alert');

        $this->actingAs($admin);

        Livewire::test(ListNotifications::class)->callTableAction('markRead', $own);

        $this->assertNotNull($own->fresh()->read_at);
    }

    public function test_generated_alerts_cannot_be_created_edited_or_deleted_from_the_panel(): void
    {
        $admin = $this->admin();
        $own = $this->notify($admin, 'Own alert');

        $this->actingAs($admin);

        $this->get('/hub/notifications/create')->assertNotFound();
        $this->get("/hub/notifications/{$own->id}/edit")->assertNotFound();
        $this->assertFalse($admin->can('create', Notification::class));
        $this->assertFalse($admin->can('update', $own));
        $this->assertFalse($admin->can('delete', $own));
        $this->assertFalse($admin->can('forceDelete', $own));
    }

    public function test_panel_mfa_rule_still_applies(): void
    {
        Permission::findOrCreate('module.adminpanel', 'web');
        $user = User::factory()->create(['is_active' => true]);
        $user->givePermissionTo('module.adminpanel');

        $this->actingAs($user)->get('/hub/notifications')->assertRedirect();
    }
}
