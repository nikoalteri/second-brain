<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_per_email(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertUnauthorized();
        }

        // Even the right password is refused while the limit is active.
        $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'secret1234'])
            ->assertStatus(429);
    }

    public function test_login_throttle_does_not_affect_other_accounts(): void
    {
        $target = User::factory()->create(['password' => bcrypt('secret1234')]);
        $other = User::factory()->create(['password' => bcrypt('secret1234')]);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => $target->email, 'password' => 'wrong-password']);
        }

        $this->postJson('/api/v1/auth/login', ['email' => $other->email, 'password' => 'secret1234'])->assertOk();
    }

    public function test_forgot_password_is_throttled(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'someone@example.com'])->assertStatus(200);
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'someone@example.com'])->assertStatus(429);
    }

    public function test_reset_password_is_throttled(): void
    {
        $payload = ['email' => 'someone@example.com', 'token' => 'invalid', 'password' => 'new-secret-1234', 'password_confirmation' => 'new-secret-1234'];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertNotSame(429, $this->postJson('/api/v1/auth/reset-password', $payload)->getStatusCode());
        }

        $this->postJson('/api/v1/auth/reset-password', $payload)->assertStatus(429);
    }

    public function test_register_is_throttled(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertNotSame(429, $this->postJson('/api/v1/auth/register', [])->getStatusCode());
        }

        $this->postJson('/api/v1/auth/register', [])->assertStatus(429);
    }

    public function test_failed_logins_do_not_block_password_recovery(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret1234')]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'wrong-password']);
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
    }
}
