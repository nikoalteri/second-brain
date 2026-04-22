<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreCreditCardExpenseRequest;
use App\Http\Requests\Api\UpdateCreditCardExpenseRequest;
use App\Http\Resources\Api\CreditCardExpenseResource;
use App\Models\CreditCard;
use App\Models\CreditCardExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class CreditCardExpenseController extends Controller
{
    public function store(StoreCreditCardExpenseRequest $request, CreditCard $creditCard): JsonResponse
    {
        $this->authorize('update', $creditCard);

        $expense = $creditCard->expenses()->create($request->validated());
        $expense->load('cycle');

        return (new CreditCardExpenseResource($expense))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateCreditCardExpenseRequest $request,
        CreditCard $creditCard,
        CreditCardExpense $expense,
    ): CreditCardExpenseResource {
        $this->assertExpenseBelongsToCard($creditCard, $expense);
        $this->authorize('update', $creditCard);

        $expense->update($request->validated());
        $expense->load('cycle');

        return new CreditCardExpenseResource($expense);
    }

    public function destroy(CreditCard $creditCard, CreditCardExpense $expense): Response
    {
        $this->assertExpenseBelongsToCard($creditCard, $expense);
        $this->authorize('update', $creditCard);

        $expense->delete();

        return response()->noContent();
    }

    private function assertExpenseBelongsToCard(CreditCard $creditCard, CreditCardExpense $expense): void
    {
        abort_unless((int) $expense->credit_card_id === (int) $creditCard->id, 404);
    }
}
