<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLoanRequest;
use App\Http\Requests\Api\UpdateLoanRequest;
use App\Http\Resources\Api\LoanResource;
use App\Enums\LoanPaymentStatus;
use App\Models\Loan;
use App\Models\LoanPayment;
use App\Services\LoanScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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
            ->when(
                ! $request->user()->hasRole('superadmin'),
                fn ($query) => $query->where('user_id', $request->user()->id)
            )
            ->allowedFilters(
                AllowedFilter::exact('status'),
                AllowedFilter::exact('account_id'),
                AllowedFilter::exact('is_variable_rate'),
            )
            ->allowedSorts('start_date', 'end_date', 'total_amount', 'remaining_amount', 'created_at')
            ->defaultSort('-created_at')
            ->cursorPaginate($this->perPage($request));

        return LoanResource::collection($loans);
    }

    /** @group Loans @authenticated */
    public function store(
        StoreLoanRequest $request,
        LoanScheduleService $loanScheduleService,
    ): JsonResponse
    {
        $this->authorize('create', Loan::class);

        // Loan and schedule are written together: a failed schedule must not leave a bare loan.
        $loan = DB::transaction(function () use ($request, $loanScheduleService) {
            $loan = Loan::create(array_merge($request->validated(), [
                'user_id' => $request->user()->id,
            ]));

            $loanScheduleService->generate($loan, onlyMissing: true);

            return $loan;
        });

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
    public function update(
        UpdateLoanRequest $request,
        Loan $loan,
        LoanScheduleService $loanScheduleService,
    ): LoanResource
    {
        $this->authorize('update', $loan);

        DB::transaction(function () use ($request, $loan, $loanScheduleService) {
            $loan->update($request->validated());
            $loanScheduleService->generate($loan, onlyMissing: true);
        });

        return new LoanResource($loan);
    }

    /** @group Loans @authenticated @response 204 {} */
    public function destroy(Request $request, Loan $loan): Response
    {
        $this->authorize('delete', $loan);

        $loan->delete();

        return response()->noContent();
    }

    /** @group Loans @authenticated */
    public function generateSchedule(Request $request, Loan $loan, LoanScheduleService $loanScheduleService): LoanResource
    {
        $this->authorize('update', $loan);

        $loanScheduleService->generate($loan, onlyMissing: true);

        $loan->load('payments');

        return new LoanResource($loan);
    }

    /**
     * Mark an installment as paid. Installments whose due date has passed are posted to the account
     * automatically but stay "pending" until the owner confirms the payment; this is that confirmation.
     * Idempotent: an installment that is already paid is returned untouched.
     *
     * @group Loans @authenticated
     */
    public function markPaymentPaid(Request $request, Loan $loan, LoanPayment $payment): LoanResource
    {
        $this->authorize('update', $loan);
        abort_unless((int) $payment->loan_id === (int) $loan->id, 404);

        $validated = $request->validate([
            // The day the money actually left; defaults to today, never in the future.
            'actual_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        if ($payment->status !== LoanPaymentStatus::PAID) {
            // The observer posts/updates the account movement and re-syncs the loan's paid count and
            // remaining amount: keep them in one transaction.
            DB::transaction(fn () => $payment->update([
                'status' => LoanPaymentStatus::PAID,
                'actual_date' => $validated['actual_date'] ?? now()->toDateString(),
            ]));
        }

        $loan->refresh()->load('payments');

        return new LoanResource($loan);
    }
}
