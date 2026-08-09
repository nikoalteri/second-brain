<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VaultCardSensitiveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'cvv' => $this->cvv,
            'pin' => $this->pin,
            'security_code' => $this->security_code,
        ];
    }
}
