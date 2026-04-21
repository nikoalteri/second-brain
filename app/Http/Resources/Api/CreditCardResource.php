<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                          => $this->id,
            'name'                        => $this->name,
            'account_id'                  => $this->account_id,
            'type'                        => $this->type instanceof \BackedEnum ? $this->type->value : $this->type,
            'credit_limit'                => $this->credit_limit !== null ? (float) $this->credit_limit : null,
            'available_credit'            => $this->available_credit,
            'fixed_payment'               => $this->fixed_payment !== null ? (float) $this->fixed_payment : null,
            'interest_rate'               => $this->interest_rate !== null ? (float) $this->interest_rate : null,
            'stamp_duty_amount'           => $this->stamp_duty_amount !== null ? (float) $this->stamp_duty_amount : null,
            'statement_day'               => $this->statement_day,
            'due_day'                     => $this->due_day,
            'skip_weekends'               => (bool) $this->skip_weekends,
            'current_balance'             => (float) $this->current_balance,
            'status'                      => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'start_date'                  => $this->start_date?->toDateString(),
            'interest_calculation_method' => $this->interest_calculation_method instanceof \BackedEnum
                ? $this->interest_calculation_method->value
                : $this->interest_calculation_method,
            'cycles'                      => $this->whenLoaded('cycles', fn () =>
                $this->cycles->map(fn ($c) => [
                    'id'             => $c->id,
                    'opening_date'   => $c->opening_date?->toDateString(),
                    'closing_date'   => $c->closing_date?->toDateString(),
                    'due_date'       => $c->due_date?->toDateString(),
                    'total_expenses' => isset($c->total_expenses) ? (float) $c->total_expenses : null,
                    'status'         => $c->status instanceof \BackedEnum ? $c->status->value : $c->status,
                ])
            ),
            'created_at'                  => $this->created_at->toISOString(),
            'updated_at'                  => $this->updated_at->toISOString(),
        ];
    }
}
