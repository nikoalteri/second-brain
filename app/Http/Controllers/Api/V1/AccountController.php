<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreAccountRequest;
use App\Http\Requests\Api\UpdateAccountRequest;
use App\Http\Resources\Api\AccountResource;
use App\Models\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Accounts
 *
 * Endpoints for managing bank accounts.
 */
class AccountController extends Controller
{
    /**
     * List all accounts for the authenticated user.
     *
     * @group Accounts
     * @authenticated
     * @queryParam filter[type] Filter by account type. Example: checking
     * @queryParam filter[is_active] Filter by active status. Example: true
     * @queryParam filter[currency] Filter by currency code. Example: EUR
     * @queryParam sort Sort field (prefix - for descending). Example: -balance
     * @queryParam cursor Opaque cursor for pagination.
     * @queryParam per_page Items per page (default 20). Example: 20
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $accounts = QueryBuilder::for(Account::class)
            ->where('user_id', $request->user()->id)
            ->allowedFilters([
                AllowedFilter::exact('type'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('currency'),
            ])
            ->allowedSorts(['name', 'balance', 'opening_balance', 'created_at'])
            ->defaultSort('-created_at')
            ->cursorPaginate($request->integer('per_page', 20));

        return AccountResource::collection($accounts);
    }

    /**
     * Create a new account.
     *
     * @group Accounts
     * @authenticated
     */
    public function store(StoreAccountRequest $request): Response
    {
        $this->authorize('create', Account::class);

        $account = Account::create(array_merge($request->validated(), [
            'user_id'         => $request->user()->id,
            'balance'         => $request->validated('opening_balance', 0),
        ]));

        return (new AccountResource($account))->response()->setStatusCode(201);
    }

    /**
     * Get a single account.
     *
     * @group Accounts
     * @authenticated
     * @urlParam account integer required The account ID. Example: 1
     */
    public function show(Request $request, Account $account): AccountResource
    {
        $this->authorize('view', $account);

        return new AccountResource($account);
    }

    /**
     * Update an account.
     *
     * @group Accounts
     * @authenticated
     * @urlParam account integer required The account ID. Example: 1
     */
    public function update(UpdateAccountRequest $request, Account $account): AccountResource
    {
        $this->authorize('update', $account);

        $account->update($request->validated());

        return new AccountResource($account);
    }

    /**
     * Delete an account.
     *
     * @group Accounts
     * @authenticated
     * @urlParam account integer required The account ID. Example: 1
     * @response 204 {}
     */
    public function destroy(Request $request, Account $account): Response
    {
        $this->authorize('delete', $account);

        $account->delete();

        return response()->noContent();
    }
}
