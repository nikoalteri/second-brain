<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserSettingResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_setting_create_page_renders(): void
    {
        Role::create(['name' => 'superadmin']);

        $user = User::factory()->create([
            'is_active' => true,
        ]);
        $user->assignRole('superadmin');

        $response = $this->actingAs($user)->get('/admin/user-settings/create');

        $response->assertOk()
            ->assertSee('Setting key')
            ->assertSee('Setting value');
    }
}
