<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use App\Services\VaultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TwoFactorHardeningTest extends TestCase
{
    use RefreshDatabase;

    private TwoFactorAuthService $service;

    private User $user;

    private string $secret;

    /** @var list<string> */
    private array $recoveryCodes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(TwoFactorAuthService::class);
        $this->secret = $this->service->generateSecretKey();
        $this->recoveryCodes = $this->service->generateRecoveryCodes();

        $this->user = User::factory()->create(['password' => Hash::make('secret1234')]);
        $this->user->forceFill([
            'two_factor_secret' => $this->secret,
            'two_factor_recovery_codes' => $this->recoveryCodes,
            'two_factor_confirmed_at' => now(),
        ])->save();
    }

    public function test_a_recovery_code_cannot_be_consumed_twice_from_stale_model_instances(): void
    {
        $first = User::find($this->user->id);
        $second = User::find($this->user->id);

        $this->assertTrue($this->service->useRecoveryCode($first, $this->recoveryCodes[0]));
        $this->assertFalse($this->service->useRecoveryCode($second, $this->recoveryCodes[0]));

        $this->assertCount(7, $this->user->fresh()->two_factor_recovery_codes);
    }

    public function test_consuming_a_recovery_code_keeps_the_other_codes_and_updates_the_given_instance(): void
    {
        $this->assertTrue($this->service->useRecoveryCode($this->user, $this->recoveryCodes[2]));

        $this->assertNotContains($this->recoveryCodes[2], $this->user->two_factor_recovery_codes);
        $this->assertContains($this->recoveryCodes[0], $this->user->fresh()->two_factor_recovery_codes);
    }

    public function test_a_totp_code_cannot_be_replayed(): void
    {
        $code = (new Google2FA)->getCurrentOtp($this->secret);

        $this->assertTrue($this->service->verifyCode($this->user, $code));
        $this->assertFalse($this->service->verifyCode($this->user, $code));
    }

    public function test_a_replayed_totp_code_is_rejected_by_the_vault_unlock(): void
    {
        Sanctum::actingAs($this->user);
        $code = (new Google2FA)->getCurrentOtp($this->secret);

        $this->postJson('/api/v1/vault/unlock', ['code' => $code])->assertOk();
        $this->postJson('/api/v1/vault/unlock', ['code' => $code])->assertStatus(422);
    }

    public function test_the_vault_pin_locks_out_after_five_wrong_attempts(): void
    {
        $vault = app(VaultService::class);
        $this->user->forceFill(['vault_pin' => '1234'])->save();

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->assertFalse($vault->verifyPin($this->user, '0000'));
        }

        try {
            $vault->verifyPin($this->user, '1234');
            $this->fail('The PIN check should be locked out.');
        } catch (HttpException $exception) {
            $this->assertSame(429, $exception->getStatusCode());
        }
    }

    public function test_a_correct_pin_resets_the_failure_counter(): void
    {
        $vault = app(VaultService::class);
        $this->user->forceFill(['vault_pin' => '1234'])->save();

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->assertFalse($vault->verifyPin($this->user, '0000'));
        }

        $this->assertTrue($vault->verifyPin($this->user, '1234'));

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->assertFalse($vault->verifyPin($this->user, '0000'));
        }

        $this->assertTrue($vault->verifyPin($this->user, '1234'));
    }
}
