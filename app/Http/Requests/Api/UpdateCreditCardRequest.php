<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreditCardRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                        => ['sometimes', 'required', 'string', 'max:255'],
            'account_id'                  => ['sometimes', 'required', 'integer', 'exists:accounts,id'],
            'type'                        => ['sometimes', 'required', Rule::in(['charge', 'revolving'])],
            'credit_limit'                => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'fixed_payment'               => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'interest_rate'               => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'stamp_duty_amount'           => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'statement_day'               => ['sometimes', 'required', 'integer', 'min:1', 'max:31'],
            'due_day'                     => ['sometimes', 'required', 'integer', 'min:1', 'max:31'],
            'skip_weekends'               => ['sometimes', 'boolean'],
            'current_balance'             => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status'                      => ['sometimes', 'required', Rule::in(['active', 'suspended', 'closed'])],
            'start_date'                  => ['sometimes', 'nullable', 'date'],
            'interest_calculation_method' => ['sometimes', 'nullable', Rule::in(['daily_balance', 'direct_monthly'])],
        ];
    }
}
