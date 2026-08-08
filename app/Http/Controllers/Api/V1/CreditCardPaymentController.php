<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CreditCardPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CreditCardPaymentResource;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use App\Services\CreditCardCycleService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CreditCardPaymentController extends Controller
{
    public function __construct(private readonly CreditCardCycleService $cycleService) {}

    public function markPaid(Request $request, CreditCard $creditCard, CreditCardPayment $payment): CreditCardPaymentResource
    {
        $this->assertPaymentBelongsToCard($creditCard, $payment);
        $this->authorize('update', $payment);

        $payment->update([
            'status' => CreditCardPaymentStatus::PAID,
            'actual_date' => $payment->actual_date ?? now()->toDateString(),
        ]);

        $payment->load('postingTransaction');

        return new CreditCardPaymentResource($payment);
    }

    public function confirmInterest(Request $request, CreditCard $creditCard, CreditCardPayment $payment): CreditCardPaymentResource
    {
        $this->assertPaymentBelongsToCard($creditCard, $payment);
        $this->authorize('update', $payment);

        abort_unless($payment->status === CreditCardPaymentStatus::PAID, 422, 'Only a paid payment can have its interest confirmed.');

        $validated = $request->validate([
            'confirmed_interest_amount' => ['required', 'numeric', 'min:0'],
        ]);

        $this->cycleService->confirmRealInterest($payment, (float) $validated['confirmed_interest_amount']);

        $payment->load('postingTransaction');

        return new CreditCardPaymentResource($payment);
    }

    public function destroy(CreditCard $creditCard, CreditCardPayment $payment): Response
    {
        $this->assertPaymentBelongsToCard($creditCard, $payment);
        $this->authorize('delete', $payment);

        $payment->delete();

        return response()->noContent();
    }

    private function assertPaymentBelongsToCard(CreditCard $creditCard, CreditCardPayment $payment): void
    {
        abort_unless((int) $payment->credit_card_id === (int) $creditCard->id, 404);
    }
}
