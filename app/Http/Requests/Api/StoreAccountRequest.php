<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Policy checked in controller
    }

    public function rules(): array
    {
        return [
            'name'            => ['required', 'string', 'max:255'],
            'type'            => ['required', 'string', 'max:50'],
            'opening_balance' => ['nullable', 'numeric', 'min:0'],
            'currency'        => ['required', 'string', 'size:3'],
            'is_active'       => ['boolean'],
        ];
    }
}
