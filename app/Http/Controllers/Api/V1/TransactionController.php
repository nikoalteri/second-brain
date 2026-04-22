<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTransactionRequest;
use App\Http\Requests\Api\UpdateTransactionRequest;
use App\Http\Resources\Api\TransactionResource;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Transactions
 *
 * Endpoints for managing financial transactions.
 */
class TransactionController extends Controller
{
    /**
     * List transactions for the authenticated user.
     *
     * @group Transactions
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $transactions = QueryBuilder::for(Transaction::class)
            ->when(
                ! $request->user()->hasRole('superadmin'),
                fn ($query) => $query->where('user_id', $request->user()->id)
            )
            ->with(['account', 'category'])
            ->allowedFilters(
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('transaction_category_id'),
                AllowedFilter::scope('date_from', 'dateFrom'),
                AllowedFilter::scope('date_to', 'dateTo'),
                AllowedFilter::exact('is_transfer'),
            )
            ->allowedSorts('date', 'amount', 'created_at', 'description')
            ->defaultSort('-date')
            ->cursorPaginate($request->integer('per_page', 20));

        return TransactionResource::collection($transactions);
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function store(StoreTransactionRequest $request): JsonResponse    {
        $this->authorize('create', Transaction::class);

        $transaction = Transaction::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        $transaction->load(['account', 'category']);

        return (new TransactionResource($transaction))->response()->setStatusCode(201);
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function show(Request $request, Transaction $transaction): TransactionResource
    {
        $this->authorize('view', $transaction);

        $transaction->load(['account', 'category']);

        return new TransactionResource($transaction);
    }

    /**
     * @group Transactions
     * @authenticated
     */
    public function update(UpdateTransactionRequest $request, Transaction $transaction): TransactionResource
    {
        $this->authorize('update', $transaction);

        $transaction->update($request->validated());
        $transaction->load(['account', 'category']);

        return new TransactionResource($transaction);
    }

    /**
     * @group Transactions
     * @authenticated
     * @response 204 {}
     */
    public function destroy(Request $request, Transaction $transaction): Response
    {
        $this->authorize('delete', $transaction);

        $transaction->delete();

        return response()->noContent();
    }
}
