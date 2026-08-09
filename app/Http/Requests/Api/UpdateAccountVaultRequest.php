<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'iban' => ['nullable', 'string', 'regex:/^[A-Z]{2}[0-9]{2}[A-Z0-9]{11,30}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'iban' => $this->iban ? strtoupper(preg_replace('/\s+/', '', (string) $this->iban)) : null,
        ]);
    }
}
