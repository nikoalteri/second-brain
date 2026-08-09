<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\VaultUnlockRequest;
use App\Models\Account;
use App\Models\CreditCard;
use App\Services\VaultService;
use Illuminate\Http\JsonResponse;

/**
 * @group Vault
 *
 * MFA-gated access to sensitive card and IBAN data. Unlock issues a short-lived vault session
 * (separate from the normal Sanctum access token) that CreditCardVaultController and
 * AccountVaultController require via the `vault.unlocked` middleware.
 */
class VaultController extends Controller
{
    public function __construct(private readonly VaultService $vault) {}

    /**
     * @group Vault
     * @authenticated
     */
    public function unlock(VaultUnlockRequest $request): JsonResponse
    {
        $token = $this->vault->unlock($request->user(), $request->validated('code'), $request);

        return response()->json([
            'vault_token' => $token,
            'expires_in' => 600,
        ]);
    }
}
