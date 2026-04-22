<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'credit_card_cycle_id' => $this->credit_card_cycle_id,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'notes' => $this->notes,
            'spent_at' => $this->spent_at?->toDateString(),
            'posted_at' => $this->posted_at?->toDateString(),
            'cycle' => $this->whenLoaded('cycle', fn () => [
                'id' => $this->cycle?->id,
                'period_month' => $this->cycle?->period_month,
            ]),
        ];
    }
}
