<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountVaultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'iban' => $this->iban,
        ];
    }
}
