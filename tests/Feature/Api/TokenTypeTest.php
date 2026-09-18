<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenTypeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: string, 1: string} access and refresh token */
    private function loginTokens(): array
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);

        $response = $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret1234'])->assertOk();

        // Separate HTTP requests do not share auth state; drop what the login left in this process.
        $this->flushSession();
        $this->app['auth']->forgetGuards();

        return [$response->json('access_token'), $response->json('refresh_token')];
    }

    public function test_refresh_token_cannot_be_used_on_rest_endpoints(): void
    {
        [, $refresh] = $this->loginTokens();

        $this->withToken($refresh)->getJson('/api/v1/auth/me')->assertUnauthorized();
        $this->withToken($refresh)->getJson('/api/v1/accounts')->assertUnauthorized();
    }

    public function test_refresh_token_cannot_be_used_on_graphql(): void
    {
        [, $refresh] = $this->loginTokens();

        $this->withToken($refresh)
            ->postJson('/graphql', ['query' => '{ accounts { data { id } } }'])
            ->assertUnauthorized();
    }

    public function test_access_token_cannot_be_used_to_refresh(): void
    {
        [$access] = $this->loginTokens();

        $this->withToken($access)->postJson('/api/v1/auth/refresh')->assertUnauthorized();
    }

    public function test_refresh_token_issues_a_working_access_token(): void
    {
        [, $refresh] = $this->loginTokens();

        $response = $this->withToken($refresh)->postJson('/api/v1/auth/refresh')->assertOk();

        $this->assertNotEmpty($response->json('access_token'));

        $this->app['auth']->forgetGuards();
        $this->withToken($response->json('access_token'))->getJson('/api/v1/auth/me')->assertOk();
    }
}
