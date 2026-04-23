<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileLocaleSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_renders_language_field(): void
    {
        Role::create(['name' => 'superadmin']);

        $user = User::factory()->create([
            'is_active' => true,
        ]);
        $user->assignRole('superadmin');

        $response = $this->actingAs($user)->get('/admin/profile');

        $response->assertOk()
            ->assertSee('Language')
            ->assertSee('English')
            ->assertSee('Italiano');
    }

    public function test_profile_page_updates_language_setting(): void
    {
        Role::create(['name' => 'superadmin']);

        $user = User::factory()->create([
            'is_active' => true,
        ]);
        $user->assignRole('superadmin');

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::actingAs($user)
            ->test(EditProfile::class)
            ->assertFormSet([
                'name' => $user->name,
                'email' => $user->email,
                'language' => 'en',
            ])
            ->fillForm([
                'name' => $user->name,
                'email' => $user->email,
                'language' => 'it',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'setting_key' => 'language',
            'setting_value' => 'it',
        ]);

        $this->assertSame('it', $user->fresh()->preferredLocale());
    }
}
