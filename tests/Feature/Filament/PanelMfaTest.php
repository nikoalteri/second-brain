<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelMfaTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $withTwoFactor): User
    {
        Role::findOrCreate('superadmin');
        $user = User::factory()->create(['is_active' => true, 'password' => Hash::make('secret1234')]);
        $user->assignRole('superadmin');

        if ($withTwoFactor) {
            $user->forceFill([
                'two_factor_secret' => app(TwoFactorAuthService::class)->generateSecretKey(),
                'two_factor_confirmed_at' => now(),
            ])->save();
        }

        return $user->fresh();
    }

    public function test_an_admin_without_a_second_factor_must_set_one_up_before_using_the_panel(): void
    {
        $user = $this->admin(withTwoFactor: false);

        $response = $this->actingAs($user)->get('/hub/accounts');

        $response->assertRedirect();
        $this->assertStringContainsString('multi-factor', (string) $response->headers->get('Location'));
    }

    public function test_an_admin_with_a_second_factor_can_use_the_panel(): void
    {
        $user = $this->admin(withTwoFactor: true);

        $this->actingAs($user)->get('/hub/accounts')->assertOk();
    }

    public function test_the_password_alone_does_not_log_an_admin_in(): void
    {
        $user = $this->admin(withTwoFactor: true);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret1234'])
            ->call('authenticate')
            ->assertNoRedirect();

        $this->assertGuest();
    }

    public function test_a_valid_totp_code_completes_the_login_with_the_same_secret_as_the_api(): void
    {
        $user = $this->admin(withTwoFactor: true);
        Filament::setCurrentPanel('admin');

        $code = (new Google2FA)->getCurrentOtp($user->two_factor_secret);

        $test = Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret1234'])
            ->call('authenticate')
            ->set('data.multiFactor.app.code', $code)
            ->call('authenticate');
        $test->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_code_does_not_log_the_admin_in(): void
    {
        $user = $this->admin(withTwoFactor: true);
        Filament::setCurrentPanel('admin');

        Livewire::test(Login::class)
            ->fillForm(['email' => $user->email, 'password' => 'secret1234'])
            ->call('authenticate')
            ->set('data.multiFactor.app.code', '000000')
            ->call('authenticate')
            ->assertHasErrors(['data.multiFactor.app.code']);

        $this->assertGuest();
    }

    public function test_the_panel_uses_the_secret_enrolled_through_the_api(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->getAppAuthenticationSecret());

        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->getAppAuthenticationSecret());
    }

    public function test_a_secret_that_is_not_confirmed_yet_does_not_count_as_enabled(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP'])->save();

        $this->assertNull($user->fresh()->getAppAuthenticationSecret());
    }

    public function test_setting_up_from_the_panel_enables_two_factor_for_the_api_too(): void
    {
        $user = User::factory()->create();

        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disabling_from_the_panel_clears_every_second_factor_field(): void
    {
        $user = $this->admin(withTwoFactor: true);
        $user->forceFill(['two_factor_recovery_codes' => ['AAAA-BBBB'], 'filament_recovery_codes' => ['hash']])->save();

        $user->saveAppAuthenticationSecret(null);

        $fresh = $user->fresh();
        $this->assertFalse($fresh->hasTwoFactorEnabled());
        $this->assertNull($fresh->two_factor_secret);
        $this->assertNull($fresh->two_factor_recovery_codes);
        $this->assertNull($fresh->filament_recovery_codes);
    }

    public function test_panel_recovery_codes_are_stored_apart_from_the_api_ones(): void
    {
        $user = $this->admin(withTwoFactor: true);
        $user->forceFill(['two_factor_recovery_codes' => ['AAAA-BBBB']])->save();

        $user->saveAppAuthenticationRecoveryCodes(['hashed-one', 'hashed-two']);

        $fresh = $user->fresh();
        $this->assertSame(['hashed-one', 'hashed-two'], $fresh->getAppAuthenticationRecoveryCodes());
        $this->assertSame(['AAAA-BBBB'], $fresh->two_factor_recovery_codes);
    }
}
