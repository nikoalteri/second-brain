<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'credit_card_cycle_id' => $this->credit_card_cycle_id,
            'due_date' => $this->due_date?->toDateString(),
            'actual_date' => $this->actual_date?->toDateString(),
            'installment_amount' => $this->installment_amount !== null ? (float) $this->installment_amount : null,
            'interest_amount' => (float) ($this->interest_amount ?? 0),
            'principal_amount' => (float) ($this->principal_amount ?? 0),
            'stamp_duty_amount' => (float) ($this->stamp_duty_amount ?? 0),
            'total_amount' => (float) ($this->total_amount ?? 0),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'notes' => $this->notes,
            'transaction_posted' => $this->relationLoaded('postingTransaction')
                ? (bool) $this->postingTransaction
                : (bool) $this->postingTransaction()->exists(),
        ];
    }
}
