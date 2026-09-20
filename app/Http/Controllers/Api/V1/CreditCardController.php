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
use Illuminate\Support\Facades\DB;
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
            ->when(
                ! $request->user()->hasRole('superadmin'),
                fn ($query) => $query->where('user_id', $request->user()->id)
            )
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('type'),
                AllowedFilter::exact('account_id'),
            )
            ->allowedSorts('name', 'credit_limit', 'current_balance', 'due_day', 'created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate($this->perPage($request));

        return CreditCardResource::collection($creditCards);
    }

    /** @group Credit Cards @authenticated */
    public function store(StoreCreditCardRequest $request): JsonResponse
    {
        $this->authorize('create', CreditCard::class);

        $creditCard = DB::transaction(fn () => CreditCard::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ])));

        return (new CreditCardResource($creditCard))->response()->setStatusCode(201);
    }

    /**
     * The card with its history. The history is bounded so a card with years of data does not
     * load every cycle, payment and expense: the most recent rows are returned, and `history`
     * reports how many exist so a client can tell when something was left out.
     *
     * @group Credit Cards
     * @authenticated
     * @queryParam cycles_limit Most recent cycles to include (default 36, max 120).
     * @queryParam payments_limit Most recent payments to include (default 200, max 500).
     * @queryParam expenses_limit Most recent expenses to include (default 500, max 1000).
     */
    public function show(Request $request, CreditCard $creditCard): CreditCardResource
    {
        $this->authorize('view', $creditCard);

        $cyclesLimit = $this->historyLimit($request, 'cycles_limit', 36, 120);
        $paymentsLimit = $this->historyLimit($request, 'payments_limit', 200, 500);
        $expensesLimit = $this->historyLimit($request, 'expenses_limit', 500, 1000);

        $cycles = $creditCard->cycles()
            // Capped per cycle (Eloquent applies this per-parent via a window function), or a
            // card with years of unusually expense-heavy cycles could still load an unbounded
            // number of rows despite the cycle count itself being capped above.
            ->with(['expenses' => fn ($expenseQuery) => $expenseQuery->orderByDesc('spent_at')->limit($expensesLimit)])
            ->orderByDesc('statement_date')
            ->limit($cyclesLimit)
            ->get();

        // Newest payments are the ones kept, but the list stays in due-date order as before.
        $payments = $creditCard->payments()
            ->with('postingTransaction')
            ->orderByDesc('due_date')
            ->limit($paymentsLimit)
            ->get()
            ->sortBy('due_date')
            ->values();

        $expenses = $creditCard->expenses()
            ->with('cycle')
            ->orderByDesc('spent_at')
            ->limit($expensesLimit)
            ->get();

        $creditCard->setRelation('cycles', $cycles)
            ->setRelation('payments', $payments)
            ->setRelation('expenses', $expenses);

        return (new CreditCardResource($creditCard))->additional(['history' => [
            'cycles' => ['limit' => $cyclesLimit, 'returned' => $cycles->count(), 'total' => $creditCard->cycles()->count()],
            'payments' => ['limit' => $paymentsLimit, 'returned' => $payments->count(), 'total' => $creditCard->payments()->count()],
            'expenses' => ['limit' => $expensesLimit, 'returned' => $expenses->count(), 'total' => $creditCard->expenses()->count()],
        ]]);
    }

    private function historyLimit(Request $request, string $key, int $default, int $max): int
    {
        return max(1, min($max, $request->integer($key, $default)));
    }

    /** @group Credit Cards @authenticated */
    public function update(UpdateCreditCardRequest $request, CreditCard $creditCard): CreditCardResource
    {
        $this->authorize('update', $creditCard);

        DB::transaction(fn () => $creditCard->update($request->validated()));

        return new CreditCardResource($creditCard);
    }

    /** @group Credit Cards @authenticated @response 204 {} */
    public function destroy(Request $request, CreditCard $creditCard): Response
    {
        $this->authorize('delete', $creditCard);

        DB::transaction(fn () => $creditCard->delete());

        return response()->noContent();
    }
}
