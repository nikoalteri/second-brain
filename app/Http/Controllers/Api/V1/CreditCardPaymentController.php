<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\CreditCardPaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\CreditCardPaymentResource;
use App\Models\CreditCard;
use App\Models\CreditCardPayment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CreditCardPaymentController extends Controller
{
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
