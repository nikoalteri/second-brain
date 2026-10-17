<?php

namespace App\Http\Resources\Api;

use App\Services\Import\ImportRowPromotionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImportRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'row_number' => $this->row_number,
            'status' => $this->status->value,
            'cells' => $this->raw_cells,
            'normalized' => $this->normalized,
            'error' => $this->error,
            'duplicate_class' => $this->duplicate_class,
            'flags' => $this->flags,
            'failure_reason' => $this->failure_reason,
            // Version of the row and its classification; an explicit confirmation must send it back.
            'review_token' => ImportRowPromotionService::signature($this->resource),
            'transaction_id' => $this->transaction_id,
            'credit_card_expense_id' => $this->credit_card_expense_id,
        ];
    }
}
