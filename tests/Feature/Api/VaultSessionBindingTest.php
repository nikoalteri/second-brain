<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class VaultSessionBindingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $secret;

    private CreditCard $card;

    protected function setUp(): void
    {
        parent::setUp();

        $service = app(TwoFactorAuthService::class);
        $this->secret = $service->generateSecretKey();

        $this->user = User::factory()->create(['password' => bcrypt('secret1234')]);
        $this->user->forceFill([
            'two_factor_secret' => $this->secret,
            'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $account = Account::factory()->create(['user_id' => $this->user->id]);
        $this->card = CreditCard::factory()->create(['user_id' => $this->user->id, 'account_id' => $account->id]);
    }

    /** A real access token; separate HTTP requests do not share auth state, so reset the guards. */
    private function newAccessToken(): string
    {
        $this->app['auth']->forgetGuards();

        return $this->user->createToken('access', ['*'], now()->addMinutes(30))->plainTextToken;
    }

    private function unlock(string $accessToken): string
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($accessToken)
            ->postJson('/api/v1/vault/unlock', ['code' => (new Google2FA)->getCurrentOtp($this->secret)])
            ->assertOk()
            ->json('vault_token');
    }

    private function readVault(string $accessToken, string $vaultToken): int
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($accessToken)
            ->withHeaders(['X-Vault-Token' => $vaultToken])
            ->getJson("/api/v1/credit-cards/{$this->card->id}/vault")
            ->getStatusCode();
    }

    public function test_the_unlock_works_for_the_access_token_that_created_it(): void
    {
        $token = $this->newAccessToken();
        $vault = $this->unlock($token);

        $this->assertSame(200, $this->readVault($token, $vault));
    }

    public function test_a_vault_token_is_useless_with_a_different_access_token(): void
    {
        $first = $this->newAccessToken();
        $vault = $this->unlock($first);

        $second = $this->newAccessToken();

        $this->assertSame(403, $this->readVault($second, $vault));
    }

    public function test_logging_out_and_back_in_does_not_keep_the_vault_unlocked(): void
    {
        $first = $this->newAccessToken();
        $vault = $this->unlock($first);

        $this->app['auth']->forgetGuards();
        $this->withToken($first)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(403, $this->readVault($this->newAccessToken(), $vault));
    }

    public function test_changing_the_vault_pin_locks_the_vault(): void
    {
        $token = $this->newAccessToken();
        $vault = $this->unlock($token);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/v1/vault/pin', ['pin' => '4321', 'password' => 'secret1234'])->assertOk();

        $this->assertSame(403, $this->readVault($token, $vault));
    }

    public function test_disabling_and_re_enabling_two_factor_does_not_revive_an_old_unlock(): void
    {
        $token = $this->newAccessToken();
        $vault = $this->unlock($token);

        $service = app(TwoFactorAuthService::class);
        $this->user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        $this->user->forceFill([
            'two_factor_secret' => $this->secret,
            'two_factor_recovery_codes' => $service->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->assertSame(403, $this->readVault($token, $vault));
    }

    public function test_a_password_change_locks_the_vault(): void
    {
        $token = $this->newAccessToken();
        $vault = $this->unlock($token);

        $this->user->forceFill(['password' => bcrypt('another-secret-1')])->save();

        $this->assertSame(403, $this->readVault($token, $vault));
    }

    public function test_unlocking_with_a_recovery_code_stays_unlocked(): void
    {
        $token = $this->newAccessToken();
        $code = $this->user->fresh()->two_factor_recovery_codes[0];

        $this->app['auth']->forgetGuards();
        $vault = $this->withToken($token)->postJson('/api/v1/vault/unlock', ['code' => $code])->assertOk()->json('vault_token');

        $this->assertSame(200, $this->readVault($token, $vault));
    }
}
