<?php

namespace App\Http\Requests\Api;

use App\Enums\CardBrand;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVaultCardSensitiveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isAmex = $this->route('vaultCard')?->brand === CardBrand::AMEX;

        return [
            'vault_pin' => ['required', 'string', 'regex:/^[0-9]{4}$/'],
            // Amex CVV/CID is 4 digits; every other network uses 3.
            'cvv' => ['nullable', 'string', $isAmex ? 'regex:/^[0-9]{4}$/' : 'regex:/^[0-9]{3}$/'],
            'pin' => ['nullable', 'string', 'regex:/^[0-9]{4,6}$/'],
            // Amex additionally prints a separate 3-digit security code; other networks don't have one.
            'security_code' => [$isAmex ? 'nullable' : 'prohibited', 'string', 'regex:/^[0-9]{3}$/'],
        ];
    }
}
