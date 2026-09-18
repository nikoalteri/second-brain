<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class RefreshTokenRotationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['password' => bcrypt('secret1234')]);
    }

    /** @return array{access: string, refresh: string} */
    private function login(): array
    {
        $response = $this->postJson('/api/v1/auth/login', ['email' => $this->user->email, 'password' => 'secret1234'])->assertOk();
        $this->resetAuthState();

        return ['access' => $response->json('access_token'), 'refresh' => $response->json('refresh_token')];
    }

    /** Separate HTTP requests do not share auth state; drop what a previous call left in this process. */
    private function resetAuthState(): void
    {
        $this->flushSession();
        $this->app['auth']->forgetGuards();
    }

    private function refresh(string $token)
    {
        $this->resetAuthState();

        return $this->withToken($token)->postJson('/api/v1/auth/refresh');
    }

    private function me(string $token)
    {
        $this->resetAuthState();

        return $this->withToken($token)->getJson('/api/v1/auth/me');
    }

    public function test_refreshing_returns_a_new_access_token_and_a_new_refresh_token(): void
    {
        $tokens = $this->login();

        $response = $this->refresh($tokens['refresh'])->assertOk();

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertNotEmpty($response->json('refresh_token'));
        $this->assertNotSame($tokens['refresh'], $response->json('refresh_token'));
        $this->me($response->json('access_token'))->assertOk();
    }

    public function test_the_new_refresh_token_can_be_used_in_turn(): void
    {
        $tokens = $this->login();

        $first = $this->refresh($tokens['refresh'])->assertOk();
        $second = $this->refresh($first->json('refresh_token'))->assertOk();

        $this->me($second->json('access_token'))->assertOk();
    }

    public function test_reusing_a_consumed_refresh_token_revokes_the_whole_session(): void
    {
        $tokens = $this->login();
        $rotated = $this->refresh($tokens['refresh'])->assertOk();

        // Well outside the grace window for simultaneous refreshes.
        $this->travel(2)->minutes();

        $this->refresh($tokens['refresh'])->assertUnauthorized();

        $this->assertSame(0, PersonalAccessToken::count());
        $this->me($rotated->json('access_token'))->assertUnauthorized();
        $this->refresh($rotated->json('refresh_token'))->assertUnauthorized();
    }

    public function test_a_simultaneous_second_refresh_is_told_to_retry_without_revoking_anything(): void
    {
        $tokens = $this->login();
        $rotated = $this->refresh($tokens['refresh'])->assertOk();

        $this->refresh($tokens['refresh'])->assertStatus(409);

        $this->me($rotated->json('access_token'))->assertOk();
        $this->refresh($rotated->json('refresh_token'))->assertOk();
    }

    public function test_an_expired_refresh_token_is_rejected_and_does_not_revoke_the_session(): void
    {
        $tokens = $this->login();

        $this->travel(8)->days();

        $this->refresh($tokens['refresh'])->assertUnauthorized();
    }

    public function test_an_unknown_token_is_rejected(): void
    {
        $this->refresh('999|not-a-real-token')->assertUnauthorized();
    }

    public function test_an_access_token_cannot_rotate(): void
    {
        $tokens = $this->login();

        $this->refresh($tokens['access'])->assertUnauthorized();
        $this->refresh($tokens['refresh'])->assertOk();
    }

    public function test_a_deactivated_user_cannot_refresh(): void
    {
        $tokens = $this->login();
        $this->user->update(['is_active' => false]);

        $this->refresh($tokens['refresh'])->assertUnauthorized();
    }
}
