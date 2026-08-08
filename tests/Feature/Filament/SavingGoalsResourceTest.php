<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\SavingGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SavingGoalsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_goals_list_renders(): void
    {
        Role::findOrCreate('superadmin');

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('superadmin');
        SavingGoal::factory()->create(['user_id' => $user->id, 'name' => 'Emergency fund']);

        $response = $this->actingAs($user)->get('/hub/saving-goals');

        $response->assertOk()->assertSee('Emergency fund');
    }

    public function test_user_cannot_open_another_users_saving_goal_edit_page(): void
    {
        \Spatie\Permission\Models\Permission::findOrCreate('module.adminpanel', 'web');
        $userA = User::factory()->create(['is_active' => true]);
        $userA->givePermissionTo('module.adminpanel');
        $userB = User::factory()->create(['is_active' => true]);

        $ownGoal = SavingGoal::factory()->create(['user_id' => $userA->id]);
        $foreignGoal = SavingGoal::factory()->create(['user_id' => $userB->id]);

        $this->actingAs($userA)->get("/hub/saving-goals/{$ownGoal->id}/edit")->assertOk();
        $this->actingAs($userA)->get("/hub/saving-goals/{$foreignGoal->id}/edit")->assertNotFound();
    }
}
