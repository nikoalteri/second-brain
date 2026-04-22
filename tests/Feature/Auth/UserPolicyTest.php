<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_superadmin_can_manage_other_users(): void
    {
        Role::create(['name' => 'superadmin']);

        $superadmin = User::factory()->create();
        $otherUser = User::factory()->create();

        $superadmin->assignRole('superadmin');

        $this->assertTrue($superadmin->can('view', $otherUser));
        $this->assertTrue($superadmin->can('update', $otherUser));
        $this->assertTrue($superadmin->can('delete', $otherUser));
        $this->assertTrue($superadmin->can('deleteAny', User::class));
    }

    public function test_regular_users_can_only_manage_their_own_record(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->assertTrue($user->can('view', $user));
        $this->assertTrue($user->can('update', $user));
        $this->assertTrue($user->can('delete', $user));

        $this->assertFalse($user->can('view', $otherUser));
        $this->assertFalse($user->can('update', $otherUser));
        $this->assertFalse($user->can('delete', $otherUser));
        $this->assertFalse($user->can('deleteAny', User::class));
    }
}
