<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'account_id'              => $this->account_id,
            'monthly_cost'            => (float) $this->monthly_cost,
            'annual_cost'             => (float) $this->annual_cost,
            'frequency'               => $this->frequency,
            'day_of_month'            => $this->day_of_month,
            'next_renewal_date'       => $this->next_renewal_date?->toDateString(),
            'auto_create_transaction' => (bool) $this->auto_create_transaction,
            'status'                  => $this->status,
            'notes'                   => $this->notes,
            'created_at'              => $this->created_at->toISOString(),
            'updated_at'              => $this->updated_at->toISOString(),
        ];
    }
}
