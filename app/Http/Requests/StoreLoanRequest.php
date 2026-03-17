<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        // L'autorizzazione viene gestita dalle policy
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'exists:accounts,id'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'monthly_payment' => ['required', 'numeric', 'min:0.01'],
            'withdrawal_day' => ['required', 'integer', 'min:1', 'max:31'],
            'skip_weekends' => ['boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'total_installments' => ['required', 'integer', 'min:1'],
            'paid_installments' => ['required', 'integer', 'min:0'],
            'remaining_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'completed', 'defaulted'])],
        ];
    }
}
