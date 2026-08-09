<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Account;
use App\Models\User;
use App\Models\VaultCard;
use App\Services\TwoFactorAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class VaultCardApiTest extends TestCase
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

    private function unlockVault(User $user, string $secret): string
    {
        $code = (new Google2FA())->getCurrentOtp($secret);

        return $this->postJson('/api/v1/vault/unlock', ['code' => $code])->json('vault_token');
    }

    public function test_vault_cards_are_inaccessible_without_unlocking(): void
    {
        $user = User::factory()->create();
        VaultCard::factory()->for($user)->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/vault-cards')->assertStatus(403);
    }

    public function test_user_can_create_a_debit_card_without_an_account(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        Sanctum::actingAs($user);
        $vaultToken = $this->unlockVault($user, $secret);

        $response = $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->postJson('/api/v1/vault-cards', [
                'name' => 'My Debit Card',
                'type' => 'debit',
                'brand' => 'visa',
                'card_number' => '4111111111111111',
                'expiry_month' => 12,
                'expiry_year' => now()->year + 3,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'My Debit Card')
            ->assertJsonPath('data.account_id', null);
    }

    public function test_user_can_create_a_prepaid_card_linked_to_an_account(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $account = Account::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);
        $vaultToken = $this->unlockVault($user, $secret);

        $response = $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->postJson('/api/v1/vault-cards', [
                'name' => 'PostePay',
                'type' => 'prepaid',
                'brand' => 'mastercard',
                'account_id' => $account->id,
            ]);

        $response->assertCreated()->assertJsonPath('data.account_id', $account->id);
    }

    public function test_user_cannot_link_a_vault_card_to_another_users_account(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        $otherAccount = Account::factory()->create();
        Sanctum::actingAs($user);
        $vaultToken = $this->unlockVault($user, $secret);

        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->postJson('/api/v1/vault-cards', [
                'name' => 'PostePay',
                'type' => 'prepaid',
                'brand' => 'mastercard',
                'account_id' => $otherAccount->id,
            ])->assertStatus(422)->assertJsonValidationErrors(['account_id']);
    }

    public function test_cvv_pin_and_security_code_require_the_vault_pin_on_top_of_the_vault_unlock(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);
        $secret = $this->enableTwoFactor($user);
        Sanctum::actingAs($user);
        $vaultCard = VaultCard::factory()->amex()->for($user)->create();

        $this->postJson('/api/v1/vault/pin', ['password' => 'secret1234', 'pin' => '4242'])->assertOk();
        $vaultToken = $this->unlockVault($user, $secret);

        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/vault-cards/{$vaultCard->id}/sensitive", ['cvv' => '1234'])
            ->assertStatus(422);

        $writeResponse = $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->putJson("/api/v1/vault-cards/{$vaultCard->id}/sensitive", [
                'vault_pin' => '4242',
                'cvv' => '1234',
                'pin' => '4321',
                'security_code' => '789',
            ]);

        $writeResponse->assertOk()
            ->assertJsonPath('data.cvv', '1234')
            ->assertJsonPath('data.security_code', '789');

        $revealResponse = $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->postJson("/api/v1/vault-cards/{$vaultCard->id}/sensitive/reveal", ['vault_pin' => '4242']);

        $revealResponse->assertOk()->assertJsonPath('data.pin', '4321');
    }

    public function test_wrong_vault_pin_locks_out_after_five_attempts_for_vault_cards(): void
    {
        $user = User::factory()->create(['password' => Hash::make('secret1234')]);
        $secret = $this->enableTwoFactor($user);
        Sanctum::actingAs($user);
        $vaultCard = VaultCard::factory()->for($user)->create();

        $this->postJson('/api/v1/vault/pin', ['password' => 'secret1234', 'pin' => '4242'])->assertOk();
        $vaultToken = $this->unlockVault($user, $secret);

        for ($i = 0; $i < 5; $i++) {
            $this->withHeaders(['X-Vault-Token' => $vaultToken])
                ->postJson("/api/v1/vault-cards/{$vaultCard->id}/sensitive/reveal", ['vault_pin' => '9999']);
        }

        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->postJson("/api/v1/vault-cards/{$vaultCard->id}/sensitive/reveal", ['vault_pin' => '4242'])
            ->assertStatus(429);
    }

    public function test_user_can_delete_a_vault_card(): void
    {
        $user = User::factory()->create();
        $secret = $this->enableTwoFactor($user);
        Sanctum::actingAs($user);
        $vaultCard = VaultCard::factory()->for($user)->create();
        $vaultToken = $this->unlockVault($user, $secret);

        $this->withHeaders(['X-Vault-Token' => $vaultToken])
            ->deleteJson("/api/v1/vault-cards/{$vaultCard->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('vault_cards', ['id' => $vaultCard->id]);
    }
}
