<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:255'],
            'account_id'              => ['required', 'integer', 'exists:accounts,id'],
            'monthly_cost'            => ['nullable', 'numeric', 'min:0'],
            'annual_cost'             => ['nullable', 'numeric', 'min:0'],
            'frequency'               => ['required', Rule::in(['monthly', 'annual', 'biennial'])],
            'day_of_month'            => ['required', 'integer', 'min:1', 'max:31'],
            'next_renewal_date'       => ['required', 'date'],
            'category_id'             => ['nullable', 'integer', 'exists:transaction_categories,id'],
            'auto_create_transaction' => ['boolean'],
            'status'                  => ['required', Rule::in(['active', 'inactive', 'cancelled'])],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ];
    }
}
