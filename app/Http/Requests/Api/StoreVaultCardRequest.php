<?php

namespace App\Http\Requests\Api;

use App\Enums\CardBrand;
use App\Enums\VaultCardType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVaultCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(VaultCardType::class)],
            'brand' => ['required', Rule::enum(CardBrand::class)],
            'account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where(function ($query) {
                    if (! auth()->user()?->hasRole('superadmin')) {
                        $query->where('user_id', auth()->id());
                    }
                }),
            ],
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
