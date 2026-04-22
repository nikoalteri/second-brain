<?php

namespace App\Http\Resources\Api;

use App\Enums\CreditCardCycleStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CreditCardCycleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period_month' => $this->period_month,
            'period_start_date' => $this->period_start_date?->toDateString(),
            'statement_date' => $this->statement_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'total_spent' => (float) ($this->total_spent ?? 0),
            'interest_amount' => (float) ($this->interest_amount ?? 0),
            'principal_amount' => (float) ($this->principal_amount ?? 0),
            'stamp_duty_amount' => (float) ($this->stamp_duty_amount ?? 0),
            'total_due' => (float) ($this->total_due ?? 0),
            'paid_amount' => (float) ($this->paid_amount ?? 0),
            'status' => $this->status instanceof \BackedEnum ? $this->status->value : $this->status,
            'can_issue' => ($this->status instanceof CreditCardCycleStatus ? $this->status : CreditCardCycleStatus::tryFrom((string) $this->status)) === CreditCardCycleStatus::OPEN,
            'expenses' => $this->whenLoaded('expenses', fn () => $this->expenses->map(fn ($expense) => [
                'id' => $expense->id,
                'amount' => (float) $expense->amount,
                'description' => $expense->description,
                'notes' => $expense->notes,
                'spent_at' => $expense->spent_at?->toDateString(),
                'posted_at' => $expense->posted_at?->toDateString(),
            ])->values()),
        ];
    }
}
