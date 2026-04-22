<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CreditCardCycleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCreditCardCycleRequest;
use App\Http\Requests\Api\UpdateCreditCardCycleRequest;
use App\Http\Resources\Api\CreditCardCycleResource;
use App\Models\CreditCard;
use App\Models\CreditCardCycle;
use App\Services\CreditCardCycleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class CreditCardCycleController extends Controller
{
    public function store(StoreCreditCardCycleRequest $request, CreditCard $creditCard): CreditCardCycleResource
    {
        $this->authorize('update', $creditCard);

        $cycle = $creditCard->cycles()->create($this->normalizeCycleData($request->validated()));
        $cycle->load('expenses');

        return new CreditCardCycleResource($cycle);
    }

    public function update(
        UpdateCreditCardCycleRequest $request,
        CreditCard $creditCard,
        CreditCardCycle $cycle,
    ): CreditCardCycleResource {
        $this->assertCycleBelongsToCard($creditCard, $cycle);
        $this->authorize('update', $cycle);

        $cycle->update($this->normalizeCycleData($request->validated(), $cycle));
        $cycle->load('expenses');

        return new CreditCardCycleResource($cycle);
    }

    public function destroy(Request $request, CreditCard $creditCard, CreditCardCycle $cycle): Response
    {
        $this->assertCycleBelongsToCard($creditCard, $cycle);
        $this->authorize('delete', $cycle);

        $cycle->delete();

        return response()->noContent();
    }

    public function issue(
        Request $request,
        CreditCard $creditCard,
        CreditCardCycle $cycle,
        CreditCardCycleService $cycleService,
    ): CreditCardCycleResource {
        $this->assertCycleBelongsToCard($creditCard, $cycle);
        $this->authorize('update', $cycle);

        if (! $cycleService->issueCycle($cycle)) {
            throw ValidationException::withMessages([
                'cycle' => 'Unable to issue cycle. Configure the card and ensure the installment covers interest.',
            ]);
        }

        $cycle->refresh()->load('expenses');

        return new CreditCardCycleResource($cycle);
    }

    private function normalizeCycleData(array $data, ?CreditCardCycle $cycle = null): array
    {
        $periodStart = array_key_exists('period_start_date', $data)
            ? Carbon::parse($data['period_start_date'])
            : $cycle?->period_start_date;
        $statementDate = array_key_exists('statement_date', $data)
            ? Carbon::parse($data['statement_date'])
            : $cycle?->statement_date;

        if (! $periodStart || ! $statementDate) {
            return $data;
        }

        if ($periodStart->isAfter($statementDate)) {
            throw ValidationException::withMessages([
                'period_start_date' => 'Period start date must be before or equal to statement date.',
            ]);
        }

        $data['period_start_date'] = $periodStart->toDateString();
        $data['period_month'] = $periodStart->format('Y-m');
        $data['statement_date'] = $statementDate->toDateString();
        $data['total_spent'] = round((float) ($data['total_spent'] ?? $cycle?->total_spent ?? 0), 2);
        $data['status'] = $data['status'] ?? ($cycle?->status instanceof \BackedEnum ? $cycle->status->value : $cycle?->status) ?? CreditCardCycleStatus::OPEN->value;

        if (array_key_exists('due_date', $data) && $data['due_date']) {
            $data['due_date'] = Carbon::parse($data['due_date'])->toDateString();
            return $data;
        }

        if ($cycle?->due_date && ! array_key_exists('due_date', $data)) {
            return $data;
        }

        $dueDate = $statementDate->copy()->day(19);

        if ($dueDate->lessThanOrEqualTo($statementDate)) {
            $dueDate = $dueDate->addMonthNoOverflow()->day(19);
        }

        $data['due_date'] = $dueDate->toDateString();

        return $data;
    }

    private function assertCycleBelongsToCard(CreditCard $creditCard, CreditCardCycle $cycle): void
    {
        abort_unless((int) $cycle->credit_card_id === (int) $creditCard->id, 404);
    }
}
