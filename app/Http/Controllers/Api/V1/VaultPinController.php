<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SetVaultPinRequest;
use App\Services\VaultService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VaultPinController extends Controller
{
    public function __construct(private readonly VaultService $vault) {}

    /**
     * @group Vault
     * @authenticated
     */
    public function store(SetVaultPinRequest $request): JsonResponse
    {
        $this->vault->setPin($request->user(), $request->validated('pin'), $request->validated('password'));

        return response()->json(['message' => 'Vault PIN set.']);
    }

    /**
     * @group Vault
     * @authenticated
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['has_vault_pin' => $request->user()->hasVaultPin()]);
    }
}
