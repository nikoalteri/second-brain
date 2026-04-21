<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'account_id'         => $this->account_id,
            'total_amount'       => (float) $this->total_amount,
            'monthly_payment'    => (float) $this->monthly_payment,
            'interest_rate'      => (float) $this->interest_rate,
            'is_variable_rate'   => (bool) $this->is_variable_rate,
            'remaining_amount'   => (float) $this->remaining_amount,
            'total_installments' => $this->total_installments,
            'paid_installments'  => $this->paid_installments,
            'status'             => $this->status,
            'start_date'         => $this->start_date?->toDateString(),
            'end_date'           => $this->end_date?->toDateString(),
            'payments'           => $this->whenLoaded('payments', fn () =>
                $this->payments->map(fn ($p) => [
                    'id'         => $p->id,
                    'due_date'   => $p->due_date?->toDateString(),
                    'amount'     => (float) $p->amount,
                    'status'     => $p->status,
                ])
            ),
            'created_at'         => $this->created_at->toISOString(),
            'updated_at'         => $this->updated_at->toISOString(),
        ];
    }
}
