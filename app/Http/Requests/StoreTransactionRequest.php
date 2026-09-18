<?php

namespace App\Http\Requests;

use App\Rules\OwnedByAuthenticatedUser;
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
            'account_id' => ['required', OwnedByAuthenticatedUser::accounts()],
            'transaction_type_id' => ['required', 'exists:transaction_types,id'],
            'transaction_category_id' => ['nullable', OwnedByAuthenticatedUser::categories()],
            'amount' => ['required', 'numeric'],
            'date' => ['required', 'date'],
            'to_account_id' => ['nullable', OwnedByAuthenticatedUser::accounts()],
            'is_transfer' => ['boolean'],
        ];
    }
}
