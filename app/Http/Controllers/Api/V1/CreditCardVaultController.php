<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateCreditCardVaultRequest;
use App\Http\Resources\Api\CreditCardVaultResource;
use App\Models\CreditCard;
use App\Services\VaultService;
use Illuminate\Http\Request;

class CreditCardVaultController extends Controller
{
    public function __construct(private readonly VaultService $vault) {}

    public function show(Request $request, CreditCard $creditCard): CreditCardVaultResource
    {
        $this->authorize('view', $creditCard);

        $this->vault->log($request->user(), 'vault.credit_card.view', $request, CreditCard::class, $creditCard->id);

        return new CreditCardVaultResource($creditCard);
    }

    public function update(UpdateCreditCardVaultRequest $request, CreditCard $creditCard): CreditCardVaultResource
    {
        $this->authorize('update', $creditCard);

        $creditCard->update($request->validated());

        $this->vault->log($request->user(), 'vault.credit_card.update', $request, CreditCard::class, $creditCard->id);

        return new CreditCardVaultResource($creditCard->fresh());
    }
}
