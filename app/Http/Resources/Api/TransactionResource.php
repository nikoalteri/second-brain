<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'account_id'              => $this->account_id,
            'transaction_type_id'     => $this->transaction_type_id,
            'transaction_category_id' => $this->transaction_category_id,
            'amount'                  => (float) $this->amount,
            'date'                    => $this->date?->toDateString(),
            'description'             => $this->description,
            'notes'                   => $this->notes,
            'is_transfer'             => (bool) $this->is_transfer,
            'account'                 => $this->whenLoaded('account', fn () => [
                'id'   => $this->account->id,
                'name' => $this->account->name,
            ]),
            'category'                => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'created_at'              => $this->created_at->toISOString(),
            'updated_at'              => $this->updated_at->toISOString(),
        ];
    }
}
