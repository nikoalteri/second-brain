<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLoanRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'             => ['sometimes', 'required', 'string', 'max:255'],
            'total_amount'     => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'monthly_payment'  => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'interest_rate'    => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'is_variable_rate' => ['sometimes', 'boolean'],
            'status'           => ['sometimes', 'required', Rule::in(['active', 'completed', 'defaulted'])],
        ];
    }
}
