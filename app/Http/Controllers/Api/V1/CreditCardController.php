<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCreditCardRequest;
use App\Http\Requests\Api\UpdateCreditCardRequest;
use App\Http\Resources\Api\CreditCardResource;
use App\Models\CreditCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Credit Cards
 *
 * Endpoints for managing credit cards.
 */
class CreditCardController extends Controller
{
    /**
     * @group Credit Cards
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $creditCards = QueryBuilder::for(CreditCard::class)
            ->where('user_id', $request->user()->id)
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('account_id'),
            )
            ->allowedSorts('name', 'credit_limit', 'current_balance', 'due_day', 'created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate($request->integer('per_page', 20));

        return CreditCardResource::collection($creditCards);
    }

    /** @group Credit Cards @authenticated */
    public function store(StoreCreditCardRequest $request): JsonResponse
    {
        $this->authorize('create', CreditCard::class);

        $creditCard = CreditCard::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        return (new CreditCardResource($creditCard))->response()->setStatusCode(201);
    }

    /** @group Credit Cards @authenticated */
    public function show(Request $request, CreditCard $creditCard): CreditCardResource
    {
        $this->authorize('view', $creditCard);

        $creditCard->load('cycles');

        return new CreditCardResource($creditCard);
    }

    /** @group Credit Cards @authenticated */
    public function update(UpdateCreditCardRequest $request, CreditCard $creditCard): CreditCardResource
    {
        $this->authorize('update', $creditCard);

        $creditCard->update($request->validated());

        return new CreditCardResource($creditCard);
    }

    /** @group Credit Cards @authenticated @response 204 {} */
    public function destroy(Request $request, CreditCard $creditCard): Response
    {
        $this->authorize('delete', $creditCard);

        $creditCard->delete();

        return response()->noContent();
    }
}
