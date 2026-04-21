<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_login_and_receive_tokens(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'secret1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 1800);

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertNotEmpty($response->json('refresh_token'));
    }

    public function test_login_with_invalid_credentials_returns_401(): void
    {
        $user = User::factory()->create(['password' => bcrypt('correct-password')]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(401)
            ->assertJson(['message' => 'Invalid credentials.']);
    }

    public function test_login_with_missing_fields_returns_422(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Validation failed.'])
            ->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_authenticated_user_can_logout_and_token_is_invalidated(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);

        // Login to get a real token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'secret1234',
        ]);
        $loginResponse->assertOk();

        // Logout with the token
        $logoutResponse = $this->withToken($loginResponse->json('access_token'))
            ->postJson('/api/v1/auth/logout');

        $logoutResponse->assertOk()
            ->assertJson(['message' => 'Logged out.']);

        // Verify all tokens are deleted from the database
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_user_can_refresh_access_token(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);

        // Login to get tokens
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => $user->email,
            'password' => 'secret1234',
        ]);
        $loginResponse->assertOk();
        $accessToken = $loginResponse->json('access_token');

        // Refresh using the access token (any valid token)
        $refreshResponse = $this->withToken($accessToken)
            ->postJson('/api/v1/auth/refresh');

        $refreshResponse->assertOk()
            ->assertJsonStructure(['access_token', 'token_type', 'expires_in'])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 1800);

        $this->assertNotEmpty($refreshResponse->json('access_token'));
    }

    public function test_unauthenticated_request_to_protected_route_returns_401_json(): void
    {
        $response = $this->getJson('/api/v1/accounts');

        $response->assertStatus(401)
            ->assertJson(['message' => 'Unauthenticated.']);
    }

    /**
     * Due to HasUserScoping global scope, accessing another user's resource
     * returns 404 (not 403) because route model binding cannot find the record.
     */
    public function test_accessing_another_users_resource_returns_404(): void
    {
        $userA = User::factory()->create();
        $userB = User::factory()->create();

        // Create userA's account WITHOUT authentication (so HasUserScoping doesn't override user_id)
        $userAAccount = Account::factory()->create(['user_id' => $userA->id]);

        // Act as userB and try to access userA's account
        Sanctum::actingAs($userB);

        $response = $this->getJson("/api/v1/accounts/{$userAAccount->id}");

        // HasUserScoping filters the account out for userB → 404
        $response->assertStatus(404)
            ->assertJson(['message' => 'Resource not found.']);
    }

    public function test_requesting_nonexistent_resource_returns_404_json(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/accounts/999999');

        $response->assertStatus(404)
            ->assertJson(['message' => 'Resource not found.']);
    }

    public function test_validation_errors_return_422_with_errors_structure(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/accounts', []);

        $response->assertStatus(422)
            ->assertJson(['message' => 'Validation failed.'])
            ->assertJsonStructure(['errors']);
    }
}
