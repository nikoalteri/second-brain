<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RevealSensitiveVaultRequest;
use App\Http\Requests\Api\UpdateSensitiveVaultRequest;
use App\Http\Resources\Api\CreditCardSensitiveVaultResource;
use App\Models\CreditCard;
use App\Services\VaultService;

/**
 * CVV and PIN specifically require the vault to be TOTP-unlocked (vault.unlocked middleware,
 * same as the rest of the card vault) AND a correct 4-digit vault PIN on every single call —
 * the extra factor the user asked for on top of the standard vault unlock, since these two
 * fields are materially more dangerous if leaked than the card number or expiry.
 */
class CreditCardSensitiveVaultController extends Controller
{
    public function __construct(private readonly VaultService $vault) {}

    public function reveal(RevealSensitiveVaultRequest $request, CreditCard $creditCard): CreditCardSensitiveVaultResource
    {
        $this->authorize('view', $creditCard);

        if (! $this->vault->verifyPin($request->user(), $request->validated('vault_pin'))) {
            abort(422, 'Incorrect vault PIN.');
        }

        $this->vault->log($request->user(), 'vault.credit_card.reveal_sensitive', $request, CreditCard::class, $creditCard->id);

        return new CreditCardSensitiveVaultResource($creditCard);
    }

    public function update(UpdateSensitiveVaultRequest $request, CreditCard $creditCard): CreditCardSensitiveVaultResource
    {
        $this->authorize('update', $creditCard);

        if (! $this->vault->verifyPin($request->user(), $request->validated('vault_pin'))) {
            abort(422, 'Incorrect vault PIN.');
        }

        $creditCard->update($request->safe()->only(['cvv', 'pin', 'security_code']));

        $this->vault->log($request->user(), 'vault.credit_card.update_sensitive', $request, CreditCard::class, $creditCard->id);

        return new CreditCardSensitiveVaultResource($creditCard->fresh());
    }
}
