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
            'account_id'              => ['nullable', 'integer', 'exists:accounts,id', 'required_without:credit_card_id'],
            'credit_card_id'          => ['nullable', 'integer', 'exists:credit_cards,id', 'required_without:account_id'],
            'billing_amount'          => ['required', 'numeric', 'min:0'],
            'subscription_frequency_id' => ['required', 'integer', Rule::exists('subscription_frequencies', 'id')->where('is_active', true)],
            'day_of_month'            => ['required', 'integer', 'min:1', 'max:31'],
            'next_renewal_date'       => ['required', 'date'],
            'category_id'             => ['nullable', 'integer', 'exists:transaction_categories,id'],
            'auto_create_transaction' => ['boolean'],
            'status'                  => ['required', Rule::in(['active', 'inactive', 'cancelled'])],
            'notes'                   => ['nullable', 'string', 'max:1000'],
        ];
    }
}
