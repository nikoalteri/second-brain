<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:50'],
            'balance' => ['nullable', 'numeric'],
            'opening_balance' => ['nullable', 'numeric'],
            'currency' => ['required', 'string', 'max:10'],
            'color' => ['nullable', 'string', 'max:10'],
            'icon' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }
}
