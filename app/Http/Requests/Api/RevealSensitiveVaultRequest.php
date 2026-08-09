<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RevealSensitiveVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vault_pin' => ['required', 'string', 'regex:/^[0-9]{4}$/'],
        ];
    }
}
