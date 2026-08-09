<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCreditCardVaultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'card_number' => ['nullable', 'string', 'regex:/^[0-9]{12,19}$/'],
            'expiry_month' => ['nullable', 'integer', 'min:1', 'max:12'],
            'expiry_year' => ['nullable', 'integer', 'min:' . now()->year, 'max:' . (now()->year + 25)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'card_number' => $this->card_number ? preg_replace('/\s+/', '', (string) $this->card_number) : null,
        ]);
    }
}
