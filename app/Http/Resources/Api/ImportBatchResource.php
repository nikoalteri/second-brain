<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Batch metadata plus, when loaded, the first rows for the mapping preview. Never exposes the
 * owner id or the stored mapping internals beyond what the client sent.
 */
class ImportBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'credit_card_id' => $this->credit_card_id,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'file_format' => $this->file_format,
            'original_filename' => $this->original_filename,
            'row_count' => $this->row_count,
            'statement_ending_balance' => $this->statement_ending_balance,
            'period_start' => $this->period_start?->toDateString(),
            'period_end' => $this->period_end?->toDateString(),
            'created_at' => $this->created_at->toISOString(),
            'preview' => $this->whenLoaded('rows', fn () => $this->rows->map(fn ($row) => [
                'row_number' => $row->row_number,
                'cells' => $row->raw_cells,
                'status' => $row->status->value,
            ])->values()),
        ];
    }
}
