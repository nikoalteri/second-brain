<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'required', 'string', 'max:255'],
            'account_id'        => ['sometimes', 'nullable', 'integer', 'exists:accounts,id', 'required_without:credit_card_id'],
            'credit_card_id'    => ['sometimes', 'nullable', 'integer', 'exists:credit_cards,id', 'required_without:account_id'],
            'billing_amount'    => ['sometimes', 'required', 'numeric', 'min:0'],
            'subscription_frequency_id' => ['sometimes', 'required', 'integer', Rule::exists('subscription_frequencies', 'id')->where('is_active', true)],
            'day_of_month'      => ['sometimes', 'required', 'integer', 'min:1', 'max:31'],
            'next_renewal_date' => ['sometimes', 'required', 'date'],
            'category_id'       => ['sometimes', 'nullable', 'integer', 'exists:transaction_categories,id'],
            'auto_create_transaction' => ['sometimes', 'boolean'],
            'status'            => ['sometimes', 'required', Rule::in(['active', 'inactive', 'cancelled'])],
            'notes'             => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
