<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditCardRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                        => ['required', 'string', 'max:255'],
            'account_id'                  => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(function ($query) {
                    if (! auth()->user()?->hasRole('superadmin')) {
                        $query->where('user_id', auth()->id());
                    }
                }),
            ],
            'type'                        => ['required', Rule::in(['charge', 'revolving'])],
            'credit_limit'                => ['nullable', 'numeric', 'min:0'],
            'fixed_payment'               => ['nullable', 'numeric', 'min:0'],
            'interest_rate'               => ['nullable', 'numeric', 'min:0', 'max:100'],
            'stamp_duty_amount'           => ['nullable', 'numeric', 'min:0'],
            'statement_day'               => ['required', 'integer', 'min:1', 'max:31'],
            'due_day'                     => ['required', 'integer', 'min:1', 'max:31'],
            'skip_weekends'               => ['boolean'],
            'current_balance'             => ['nullable', 'numeric', 'min:0'],
            'status'                      => ['required', Rule::in(['active', 'suspended', 'closed'])],
            'start_date'                  => ['nullable', 'date'],
            'interest_calculation_method' => ['nullable', Rule::in(['daily_balance', 'direct_monthly'])],
        ];
    }
}
