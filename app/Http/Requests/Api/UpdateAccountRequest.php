<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'type'      => ['sometimes', 'required', 'string', 'max:50'],
            'currency'  => ['sometimes', 'required', 'string', 'size:3'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
