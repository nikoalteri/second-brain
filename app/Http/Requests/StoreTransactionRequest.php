<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['required', 'exists:accounts,id'],
            'transaction_type_id' => ['required', 'exists:transaction_types,id'],
            'transaction_category_id' => ['nullable', 'exists:transaction_categories,id'],
            'amount' => ['required', 'numeric'],
            'date' => ['required', 'date'],
            'to_account_id' => ['nullable', 'exists:accounts,id'],
            'is_transfer' => ['boolean'],
        ];
    }
}
