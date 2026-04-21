<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLoanRequest;
use App\Http\Requests\Api\UpdateLoanRequest;
use App\Http\Resources\Api\LoanResource;
use App\Models\Loan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * @group Loans
 *
 * Endpoints for managing loans.
 */
class LoanController extends Controller
{
    /**
     * @group Loans
     * @authenticated
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $loans = QueryBuilder::for(Loan::class)
            ->where('user_id', $request->user()->id)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('is_variable_rate'),
            ])
            ->allowedSorts(['start_date', 'end_date', 'total_amount', 'remaining_amount', 'created_at'])
            ->defaultSort('-created_at')
            ->cursorPaginate($request->integer('per_page', 20));

        return LoanResource::collection($loans);
    }

    /** @group Loans @authenticated */
    public function store(StoreLoanRequest $request): Response
    {
        $this->authorize('create', Loan::class);

        $loan = Loan::create(array_merge($request->validated(), [
            'user_id' => $request->user()->id,
        ]));

        return (new LoanResource($loan))->response()->setStatusCode(201);
    }

    /** @group Loans @authenticated */
    public function show(Request $request, Loan $loan): LoanResource
    {
        $this->authorize('view', $loan);

        $loan->load('payments');

        return new LoanResource($loan);
    }

    /** @group Loans @authenticated */
    public function update(UpdateLoanRequest $request, Loan $loan): LoanResource
    {
        $this->authorize('update', $loan);

        $loan->update($request->validated());

        return new LoanResource($loan);
    }

    /** @group Loans @authenticated @response 204 {} */
    public function destroy(Request $request, Loan $loan): Response
    {
        $this->authorize('delete', $loan);

        $loan->delete();

        return response()->noContent();
    }
}
