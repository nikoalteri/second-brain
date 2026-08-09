<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\CreditCard;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class VaultApiTest extends TestCase
{
    use RefreshDatabase;

    private function enableTwoFactor(User $user): string
    {
        $secret = app(TwoFactorAuthService::class)->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => app(TwoFactorAuthService::class)->generateRecoveryCodes(),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    public function test_vault_unlock_is_rejected_without_two_factor_enabled(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/vault/unlock', ['code' => '123456'])
            ->assertStatus(403);
    }

    public function test_vault_data_is_inaccessible_without_unlocking(): void
    {
        $user = User::factory()->create();
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/credit-cards/{$card->id}/vault")->assertStatus(403);
        $this->getJson("/api/v1/accounts/{$account->id}/vault")->assertStatus(403);
    }

    public function test_user_can_unlock_vault_and_write_read_credit_card_data(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        Sanctum::actingAs($user);

        $code = (new Google2FA())->getCurrentOtp($secret);
        $unlock = $this->postJson('/api/v1/vault/unlock', ['code' => $code]);
        $unlock->assertOk()->assertJsonStructure(['vault_token', 'expires_in']);

        $vaultToken = $unlock->json('vault_token');

        $writeResponse = $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/credit-cards/{$card->id}/vault", [
                'card_number' => '378282246310005',
                'expiry_month' => 12,
                'expiry_year' => now()->year + 3,
            ]);

        $writeResponse->assertOk()->assertJsonPath('data.card_number', '378282246310005');

        $readResponse = $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->getJson("/api/v1/credit-cards/{$card->id}/vault");

        $readResponse->assertOk()->assertJsonPath('data.card_number', '378282246310005');

        $this->assertNotSame(
            '378282246310005',
            \DB::table('credit_cards')->where('id', $card->id)->value('card_number'),
        );
    }

    public function test_user_can_unlock_vault_and_write_read_account_iban(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $account = Account::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        $code = (new Google2FA())->getCurrentOtp($secret);
        $vaultToken = $this->postJson('/api/v1/vault/unlock', ['code' => $code])->json('vault_token');

        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/accounts/{$account->id}/vault", ['iban' => 'it60 x054 2811 1010 0000 0123 456'])
            ->assertOk()
            ->assertJsonPath('data.iban', 'IT60X0542811101000000123456');
    }

    public function test_invalid_code_does_not_unlock_the_vault(): void
    {
        $user = User::factory()->create();
        $this->enableTwoFactor($user);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/vault/unlock', ['code' => '000000'])
            ->assertStatus(422);
    }

    public function test_vault_token_does_not_work_for_a_different_user(): void
    {
        $userA = User::factory()->create();
        $secretA = $this->enableTwoFactor($userA);
        $userB = User::factory()->create();
        $accountB = Account::factory()->create(['user_id' => $userB->id]);

        Sanctum::actingAs($userA);
        $code = (new Google2FA())->getCurrentOtp($secretA);
        $vaultToken = $this->postJson('/api/v1/vault/unlock', ['code' => $code])->json('vault_token');

        Sanctum::actingAs($userB);
        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->getJson("/api/v1/accounts/{$accountB->id}/vault")
            ->assertStatus(403);
    }

    public function test_card_number_rejects_invalid_format(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        Sanctum::actingAs($user);

        $code = (new Google2FA())->getCurrentOtp($secret);
        $vaultToken = $this->postJson('/api/v1/vault/unlock', ['code' => $code])->json('vault_token');

        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/credit-cards/{$card->id}/vault", ['card_number' => 'not-a-card'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['card_number']);
    }

    public function test_user_can_set_a_vault_pin_with_correct_password(): void
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('secret1234')]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/vault/pin', ['password' => 'wrong', 'pin' => '4242'])
            ->assertStatus(422);
        $this->assertFalse($user->fresh()->hasVaultPin());

        $this->postJson('/api/v1/vault/pin', ['password' => 'secret1234', 'pin' => '4242'])
            ->assertOk();
        $this->assertTrue($user->fresh()->hasVaultPin());
    }

    public function test_cvv_and_pin_require_the_vault_pin_on_top_of_the_vault_unlock(): void
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('secret1234')]);
        $secret = $this->enableTwoFactor($user);
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/vault/pin', ['password' => 'secret1234', 'pin' => '4242'])->assertOk();

        $code = (new Google2FA())->getCurrentOtp($secret);
        $vaultToken = $this->postJson('/api/v1/vault/unlock', ['code' => $code])->json('vault_token');

        // Vault is unlocked, but writing CVV/PIN without the vault_pin still fails validation.
        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/credit-cards/{$card->id}/vault/sensitive", ['cvv' => '123', 'pin' => '4321'])
            ->assertStatus(422);

        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/credit-cards/{$card->id}/vault/sensitive", [
                'vault_pin' => '0000',
                'cvv' => '123',
                'pin' => '4321',
            ])->assertStatus(422);

        $writeResponse = $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/credit-cards/{$card->id}/vault/sensitive", [
                'vault_pin' => '4242',
                'cvv' => '123',
                'pin' => '4321',
            ]);

        $writeResponse->assertOk()
            ->assertJsonPath('data.cvv', '123')
            ->assertJsonPath('data.pin', '4321');

        $revealResponse = $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->postJson("/api/v1/credit-cards/{$card->id}/vault/sensitive/reveal", ['vault_pin' => '4242']);

        $revealResponse->assertOk()->assertJsonPath('data.cvv', '123');
    }

    public function test_amex_cvv_is_four_digits_and_allows_a_security_code(): void
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('secret1234')]);
        $secret = $this->enableTwoFactor($user);
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->amex()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/vault/pin', ['password' => 'secret1234', 'pin' => '4242'])->assertOk();

        $code = (new Google2FA())->getCurrentOtp($secret);
        $vaultToken = $this->postJson('/api/v1/vault/unlock', ['code' => $code])->json('vault_token');

        // A 3-digit CVV is rejected for Amex.
        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/credit-cards/{$card->id}/vault/sensitive", [
                'vault_pin' => '4242',
                'cvv' => '123',
            ])->assertStatus(422);

        $writeResponse = $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/credit-cards/{$card->id}/vault/sensitive", [
                'vault_pin' => '4242',
                'cvv' => '1234',
                'security_code' => '789',
            ]);

        $writeResponse->assertOk()
            ->assertJsonPath('data.cvv', '1234')
            ->assertJsonPath('data.security_code', '789');
    }

    public function test_security_code_is_rejected_for_non_amex_cards(): void
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('secret1234')]);
        $secret = $this->enableTwoFactor($user);
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/vault/pin', ['password' => 'secret1234', 'pin' => '4242'])->assertOk();

        $code = (new Google2FA())->getCurrentOtp($secret);
        $vaultToken = $this->postJson('/api/v1/vault/unlock', ['code' => $code])->json('vault_token');

        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/credit-cards/{$card->id}/vault/sensitive", [
                'vault_pin' => '4242',
                'cvv' => '123',
                'security_code' => '789',
            ])->assertStatus(422)
            ->assertJsonValidationErrors(['security_code']);
    }

    public function test_wrong_vault_pin_locks_out_after_five_attempts(): void
    {
        $user = User::factory()->create(['password' => \Illuminate\Support\Facades\Hash::make('secret1234')]);
        $secret = $this->enableTwoFactor($user);
        $account = Account::factory()->create(['user_id' => $user->id]);
        $card = CreditCard::factory()->create(['user_id' => $user->id, 'account_id' => $account->id]);
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/vault/pin', ['password' => 'secret1234', 'pin' => '4242'])->assertOk();

        $code = (new Google2FA())->getCurrentOtp($secret);
        $vaultToken = $this->postJson('/api/v1/vault/unlock', ['code' => $code])->json('vault_token');

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['X-Vault-Token' => $vaultToken])
                ->postJson("/api/v1/credit-cards/{$card->id}/vault/sensitive/reveal", ['vault_pin' => '9999']);
        }

        // Even the correct PIN is now locked out.
        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->postJson("/api/v1/credit-cards/{$card->id}/vault/sensitive/reveal", ['vault_pin' => '4242'])
            ->assertStatus(429);
    }
}
