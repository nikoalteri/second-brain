<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class InactiveUserTest extends TestCase
{
    use RefreshDatabase;

    private function resetAuthState(): void
    {
        // Separate HTTP requests do not share auth state; drop what a login left in this process.
        $this->flushSession();
        $this->app['auth']->forgetGuards();
    }

    public function test_inactive_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234'), 'is_active' => false]);

        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret1234'])
            ->assertForbidden();

        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_deactivating_a_user_revokes_existing_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret1234'])->assertOk();
        $this->resetAuthState();

        $user->update(['is_active' => false]);

        $this->assertSame(0, PersonalAccessToken::count());
        $this->withToken($login->json('access_token'))->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->resetAuthState();
        $this->withToken($login->json('refresh_token'))->postJson('/api/v1/auth/refresh')->assertUnauthorized();
    }

    public function test_token_of_a_user_deactivated_without_model_events_is_rejected(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret1234'])->assertOk();
        $this->resetAuthState();

        // Bypasses Eloquent events, e.g. a bulk update or a direct SQL change.
        User::query()->whereKey($user->id)->update(['is_active' => false]);

        $this->withToken($login->json('access_token'))->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_deactivated_user_cannot_use_graphql(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret1234'])->assertOk();
        $this->resetAuthState();

        User::query()->whereKey($user->id)->update(['is_active' => false]);

        $this->withToken($login->json('access_token'))
            ->postJson('/graphql', ['query' => '{ accounts { data { id } } }'])
            ->assertUnauthorized();
    }

    public function test_active_user_is_unaffected(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);
        $login = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret1234'])->assertOk();
        $this->resetAuthState();

        $this->withToken($login->json('access_token'))->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_user_deactivated_after_the_password_step_cannot_complete_two_factor_login(): void
    {
        $service = app(TwoFactorAuthService::class);
        $recoveryCodes = $service->generateRecoveryCodes();
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);
        $user->forceFill([
            'two_factor_secret' => $service->generateSecretKey(),
            'two_factor_recovery_codes' => $recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        $challenge = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret1234'])
            ->assertOk()->json('two_factor_token');
        $this->resetAuthState();

        $user->update(['is_active' => false]);

        $this->postJson('/api/v1/auth/two-factor/login', ['two_factor_token' => $challenge, 'code' => $recoveryCodes[0]])
            ->assertForbidden();

        $this->assertSame(0, PersonalAccessToken::count());
    }
}
