<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RevealVaultCardSensitiveRequest;
use App\Http\Requests\Api\UpdateVaultCardSensitiveRequest;
use App\Http\Resources\Api\VaultCardSensitiveResource;
use App\Models\VaultCard;
use App\Services\VaultService;

/**
 * Mirror of CreditCardSensitiveVaultController: cvv/pin/security_code require the vault to be
 * TOTP-unlocked (vault.unlocked middleware) AND a correct 4-digit vault PIN on every call.
 */
class VaultCardSensitiveController extends Controller
{
    public function __construct(private readonly VaultService $vault) {}

    public function reveal(RevealVaultCardSensitiveRequest $request, VaultCard $vaultCard): VaultCardSensitiveResource
    {
        $this->authorize('view', $vaultCard);

        if (! $this->vault->verifyPin($request->user(), $request->validated('vault_pin'))) {
            abort(422, 'Incorrect vault PIN.');
        }

        $this->vault->log($request->user(), 'vault.vault_card.reveal_sensitive', $request, VaultCard::class, $vaultCard->id);

        return new VaultCardSensitiveResource($vaultCard);
    }

    public function update(UpdateVaultCardSensitiveRequest $request, VaultCard $vaultCard): VaultCardSensitiveResource
    {
        $this->authorize('update', $vaultCard);

        if (! $this->vault->verifyPin($request->user(), $request->validated('vault_pin'))) {
            abort(422, 'Incorrect vault PIN.');
        }

        $vaultCard->update($request->safe()->only(['cvv', 'pin', 'security_code']));

        $this->vault->log($request->user(), 'vault.vault_card.update_sensitive', $request, VaultCard::class, $vaultCard->id);

        return new VaultCardSensitiveResource($vaultCard->fresh());
    }
}
