<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorAuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_enable_and_confirm_two_factor_auth(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $enableResponse = $this->postJson('/api/v1/auth/two-factor/enable');
        $enableResponse->assertOk()->assertJsonStructure(['secret', 'otpauth_url']);

        $secret = $enableResponse->json('secret');
        $code = (new Google2FA())->getCurrentOtp($secret);

        $confirmResponse = $this->postJson('/api/v1/auth/two-factor/confirm', ['code' => $code]);

        $confirmResponse->assertOk()->assertJsonStructure(['message', 'recovery_codes']);
        $this->assertCount(8, $confirmResponse->json('recovery_codes'));
        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirm_rejects_an_invalid_code(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/two-factor/enable')->assertOk();

        $this->postJson('/api/v1/auth/two-factor/confirm', ['code' => '000000'])
            ->assertStatus(422);

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_login_with_two_factor_enabled_requires_a_challenge_step(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);
        $secret = app(TwoFactorAuthService::class)->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => app(TwoFactorAuthService::class)->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret1234',
        ]);

        $loginResponse->assertOk()
            ->assertJsonPath('two_factor_required', true)
            ->assertJsonStructure(['two_factor_token']);
        $this->assertArrayNotHasKey('access_token', $loginResponse->json());

        $code = (new Google2FA())->getCurrentOtp($secret);

        $verifyResponse = $this->postJson('/api/v1/auth/two-factor/login', [
            'two_factor_token' => $loginResponse->json('two_factor_token'),
            'code' => $code,
        ]);

        $verifyResponse->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
    }

    public function test_two_factor_login_accepts_a_recovery_code_once(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);
        $recoveryCodes = app(TwoFactorAuthService::class)->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => app(TwoFactorAuthService::class)->generateSecretKey(),
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret1234',
        ])->assertOk();

        $token = $loginResponse->json('two_factor_token');

        $this->postJson('/api/v1/auth/two-factor/login', [
            'two_factor_token' => $token,
            'code' => $recoveryCodes[0],
        ])->assertOk();

        $this->assertCount(7, $user->fresh()->two_factor_recovery_codes);

        // The challenge token was consumed by the first successful verification.
        $this->postJson('/api/v1/auth/two-factor/login', [
            'two_factor_token' => $token,
            'code' => $recoveryCodes[1],
        ])->assertStatus(422);
    }

    public function test_two_factor_login_rejects_an_invalid_code(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);
        $user->forceFill([
            'two_factor_secret' => app(TwoFactorAuthService::class)->generateSecretKey(),
            'two_factor_recovery_codes' => app(TwoFactorAuthService::class)->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'secret1234',
        ])->assertOk();

        $this->postJson('/api/v1/auth/two-factor/login', [
            'two_factor_token' => $loginResponse->json('two_factor_token'),
            'code' => '000000',
        ])->assertStatus(422);
    }

    public function test_user_can_disable_two_factor_with_correct_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);
        $user->forceFill([
            'two_factor_secret' => app(TwoFactorAuthService::class)->generateSecretKey(),
            'two_factor_recovery_codes' => app(TwoFactorAuthService::class)->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/two-factor/disable', ['password' => 'wrong'])
            ->assertStatus(422);
        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());

        $this->postJson('/api/v1/auth/two-factor/disable', ['password' => 'secret1234'])
            ->assertOk();
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_user_can_regenerate_recovery_codes(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);
        $originalCodes = app(TwoFactorAuthService::class)->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_secret' => app(TwoFactorAuthService::class)->generateSecretKey(),
            'two_factor_recovery_codes' => $originalCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/auth/two-factor/recovery-codes', ['password' => 'secret1234']);

        $response->assertOk();
        $newCodes = $response->json('recovery_codes');
        $this->assertCount(8, $newCodes);
        $this->assertNotSame($originalCodes, $newCodes);
    }
}
