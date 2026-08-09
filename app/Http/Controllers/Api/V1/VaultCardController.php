<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreVaultCardRequest;
use App\Http\Requests\Api\UpdateVaultCardRequest;
use App\Http\Resources\Api\VaultCardResource;
use App\Models\VaultCard;
use App\Services\VaultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * CRUD for debit/prepaid cards censiti nel Vault. Unlike CreditCardController, every action here
 * sits behind the vault.unlocked middleware (see routes/api.php) — even listing/creating, since
 * card_number is already sensitive and there's no "non-vault" view of this entity worth exposing.
 */
class VaultCardController extends Controller
{
    public function __construct(private readonly VaultService $vault) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $vaultCards = VaultCard::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return VaultCardResource::collection($vaultCards);
    }

    public function store(StoreVaultCardRequest $request): JsonResponse
    {
        $this->authorize('create', VaultCard::class);

        $vaultCard = VaultCard::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        $this->vault->log($request->user(), 'vault.vault_card.create', $request, VaultCard::class, $vaultCard->id);

        return (new VaultCardResource($vaultCard))->response()->setStatusCode(201);
    }

    public function update(UpdateVaultCardRequest $request, VaultCard $vaultCard): VaultCardResource
    {
        $this->authorize('update', $vaultCard);

        $vaultCard->update($request->validated());

        $this->vault->log($request->user(), 'vault.vault_card.update', $request, VaultCard::class, $vaultCard->id);

        return new VaultCardResource($vaultCard->fresh());
    }

    public function destroy(Request $request, VaultCard $vaultCard): Response
    {
        $this->authorize('delete', $vaultCard);

        $vaultCard->delete();

        $this->vault->log($request->user(), 'vault.vault_card.delete', $request, VaultCard::class, $vaultCard->id);

        return response()->noContent();
    }
}
