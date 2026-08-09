<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateAccountVaultRequest;
use App\Http\Resources\Api\AccountVaultResource;
use App\Models\Account;
use App\Services\VaultService;
use Illuminate\Http\Request;

class AccountVaultController extends Controller
{
    public function __construct(private readonly VaultService $vault) {}

    public function show(Request $request, Account $account): AccountVaultResource
    {
        $this->authorize('view', $account);

        $this->vault->log($request->user(), 'vault.account.view', $request, Account::class, $account->id);

        return new AccountVaultResource($account);
    }

    public function update(UpdateAccountVaultRequest $request, Account $account): AccountVaultResource
    {
        $this->authorize('update', $account);

        $account->update($request->validated());

        $this->vault->log($request->user(), 'vault.account.update', $request, Account::class, $account->id);

        return new AccountVaultResource($account->fresh());
    }
}
