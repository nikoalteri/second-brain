<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Spatie\Permission\Models\Role;
use App\Models\UserSetting;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_login_and_receive_tokens(): void
    {
        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'name' => 'Jane Doe',
            'phone' => null,
            'tax_code' => 'RSSMRA80A01H501U',
            'password' => bcrypt('secret1234'),
        ]);

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
                'user' => ['id', 'name', 'first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'tax_code', 'roles', 'is_admin', 'settings'],
            ])
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', 1800)
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.first_name', 'Jane')
            ->assertJsonPath('user.last_name', 'Doe')
            ->assertJsonPath('user.phone_country_code', '+39')
            ->assertJsonPath('user.phone_number', '')
            ->assertJsonPath('user.tax_code', 'RSSMRA80A01H501U')
            ->assertJsonPath('user.is_admin', false)
            ->assertJsonPath('user.settings.theme', 'system')
            ->assertJsonPath('user.settings.notifications', 'all')
            ->assertJsonPath('user.settings.privacy', 'visible');

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertNotEmpty($response->json('refresh_token'));
    }

    public function test_guest_can_register_and_receive_tokens(): void
    {
        Role::findOrCreate('user');

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario@example.com',
            'phone_country_code' => '+39',
            'phone_number' => '1234567890',
            'date_of_birth' => '1980-01-01',
            'tax_code' => 'rssmra80a01h501u',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'Mario Rossi')
            ->assertJsonPath('user.first_name', 'Mario')
            ->assertJsonPath('user.last_name', 'Rossi')
            ->assertJsonPath('user.phone', '+391234567890')
            ->assertJsonPath('user.phone_country_code', '+39')
            ->assertJsonPath('user.phone_number', '1234567890')
            ->assertJsonPath('user.tax_code', 'RSSMRA80A01H501U');

        $this->assertDatabaseHas('users', [
            'email' => 'mario@example.com',
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'tax_code' => 'RSSMRA80A01H501U',
        ]);

        $user = User::where('email', 'mario@example.com')->firstOrFail();
        $this->assertTrue($user->hasRole('user'));
    }

    public function test_registration_rejects_duplicate_tax_code(): void
    {
        User::factory()->create(['tax_code' => 'RSSMRA80A01H501U']);

        $response = $this->postJson('/api/v1/auth/register', [
            'first_name' => 'Luigi',
            'last_name' => 'Verdi',
            'email' => 'luigi@example.com',
            'tax_code' => 'RSSMRA80A01H501U',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tax_code']);
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

    public function test_authenticated_user_can_fetch_profile_with_admin_flag(): void
    {
        Role::create(['name' => 'superadmin']);

        $user = User::factory()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'name' => 'Admin User',
            'tax_code' => 'VRDLGU90B11F205X',
        ]);
        $user->assignRole('superadmin');

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('user.email', $user->email)
            ->assertJsonPath('user.first_name', 'Admin')
            ->assertJsonPath('user.last_name', 'User')
            ->assertJsonPath('user.tax_code', 'VRDLGU90B11F205X')
            ->assertJsonPath('user.is_admin', true)
            ->assertJsonPath('user.roles.0', 'superadmin')
            ->assertJsonPath('user.settings.theme', 'system');
    }

    public function test_authenticated_user_can_update_profile_details(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'tax_code' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/profile', [
            'first_name' => 'Maria',
            'last_name' => 'Bianchi',
            'email' => 'maria@example.com',
            'phone_country_code' => '+39',
            'phone_number' => '0212345678',
            'date_of_birth' => '1990-04-12',
            'tax_code' => 'BNCMRA90D52F205Z',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.name', 'Maria Bianchi')
            ->assertJsonPath('user.email', 'maria@example.com')
            ->assertJsonPath('user.phone', '+390212345678')
            ->assertJsonPath('user.phone_country_code', '+39')
            ->assertJsonPath('user.phone_number', '0212345678')
            ->assertJsonPath('user.date_of_birth', '1990-04-12')
            ->assertJsonPath('user.tax_code', 'BNCMRA90D52F205Z');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Maria Bianchi',
            'first_name' => 'Maria',
            'last_name' => 'Bianchi',
            'email' => 'maria@example.com',
            'phone' => '+390212345678',
            'tax_code' => 'BNCMRA90D52F205Z',
            'email_verified_at' => null,
        ]);
    }

    public function test_authenticated_user_profile_rejects_duplicate_tax_code(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create(['tax_code' => 'RSSMRA80A01H501U']);
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/profile', [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'phone_country_code' => '+39',
            'phone_number' => '3471234567',
            'date_of_birth' => optional($user->date_of_birth)->toDateString(),
            'tax_code' => $otherUser->tax_code,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tax_code']);
    }

    public function test_authenticated_user_can_update_frontend_settings(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/settings', [
            'theme' => 'dark',
            'notifications' => 'important_only',
            'privacy' => 'private',
        ]);

        $response->assertOk()
            ->assertJsonPath('user.settings.theme', 'dark')
            ->assertJsonPath('user.settings.notifications', 'important_only')
            ->assertJsonPath('user.settings.privacy', 'private');

        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'setting_key' => UserSetting::KEY_THEME,
            'setting_value' => 'dark',
        ]);
        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'setting_key' => UserSetting::KEY_NOTIFICATIONS,
            'setting_value' => 'important_only',
        ]);
        $this->assertDatabaseHas('user_settings', [
            'user_id' => $user->id,
            'setting_key' => UserSetting::KEY_PRIVACY,
            'setting_value' => 'private',
        ]);
    }

    public function test_authenticated_user_settings_rejects_invalid_values(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/settings', [
            'theme' => 'auto',
            'notifications' => 'all',
            'privacy' => 'visible',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['theme']);
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

    public function test_forgot_password_sends_reset_link_when_email_exists(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'reset@example.com']);

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'reset@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'If your email exists in our system, you will receive a password reset link shortly.',
            ]);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_forgot_password_does_not_leak_unknown_email(): void
    {
        Notification::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ]);

        $response->assertOk()
            ->assertJson([
                'message' => 'If your email exists in our system, you will receive a password reset link shortly.',
            ]);

        Notification::assertNothingSent();
    }

    public function test_user_can_reset_password_with_valid_token(): void
    {
        $user = User::factory()->create([
            'email' => 'reset@example.com',
            'password' => bcrypt('old-password'),
        ]);
        $user->createToken('access', ['*']);
        $token = Password::broker()->createToken($user);

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'reset@example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertOk()
            ->assertJson(['message' => 'Password reset successfully.']);

        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'reset@example.com',
            'password' => 'new-password',
        ]);

        $loginResponse->assertOk();
    }
}
