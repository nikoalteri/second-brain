<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isChargeCard = ($this->type instanceof \BackedEnum ? $this->type->value : $this->type) === 'charge';

        return [
            'id'                          => $this->id,
            'name'                        => $this->name,
            'account_id'                  => $this->account_id,
            'type'                        => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'credit_limit'                => $this->credit_limit !== null ? (float) $this->credit_limit : null,
            'available_credit'            => $this->available_credit,
            'fixed_payment'               => ! $isChargeCard && $this->fixed_payment !== null ? (float) $this->fixed_payment : null,
            'interest_rate'               => ! $isChargeCard && $this->interest_rate !== null ? (float) $this->interest_rate : null,
            'stamp_duty_amount'           => $this->stamp_duty_amount !== null ? (float) $this->stamp_duty_amount : null,
            'statement_day'               => $this->statement_day,
            'due_day'                     => $this->due_day,
            'skip_weekends'               => (bool) $this->skip_weekends,
            'current_balance'             => (float) $this->current_balance,
            'status'                      => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'start_date'                  => $this->start_date?->toDateString(),
            'interest_calculation_method' => ! $isChargeCard && $this->interest_calculation_method instanceof \BackedEnum
                ? $this->interest_calculation_method->value
                : (! $isChargeCard ? $this->interest_calculation_method : null),
            'cycles'                      => CreditCardCycleResource::collection($this->whenLoaded('cycles')),
            'payments'                    => CreditCardPaymentResource::collection($this->whenLoaded('payments')),
            'expenses'                    => CreditCardExpenseResource::collection($this->whenLoaded('expenses')),
            'created_at'                  => $this->created_at->toISOString(),
            'updated_at'                  => $this->updated_at->toISOString(),
        ];
    }
}
