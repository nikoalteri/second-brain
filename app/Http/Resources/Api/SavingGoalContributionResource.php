<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavingGoalContributionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'saving_goal_id' => $this->saving_goal_id,
            'amount' => (float) $this->amount,
            'date' => $this->date?->toDateString(),
            'notes' => $this->notes,
            'account' => $this->whenLoaded('account', fn () => $this->account ? [
                'id' => $this->account->id,
                'name' => $this->account->name,
            ] : null),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
