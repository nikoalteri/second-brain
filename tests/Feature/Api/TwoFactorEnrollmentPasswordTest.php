<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwoFactorEnrollmentPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabling_two_factor_requires_the_account_password(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/auth/two-factor/enable')->assertUnprocessable();
        $this->postJson('/api/v1/auth/two-factor/enable', ['password' => 'wrong-password'])->assertUnprocessable();
        $this->assertNull($user->fresh()->two_factor_secret);

        $this->postJson('/api/v1/auth/two-factor/enable', ['password' => 'secret1234'])
            ->assertOk()
            ->assertJsonStructure(['secret', 'otpauth_url']);
    }
}
