<?php

namespace App\Http\Requests\Api;

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
            'account_id'              => ['required', 'integer', OwnedByAuthenticatedUser::accounts()],
            'transaction_type_id'     => ['required', 'integer', 'exists:transaction_types,id'],
            'transaction_category_id' => ['nullable', 'integer', OwnedByAuthenticatedUser::categories()],
            'amount'                  => ['required', 'numeric', 'min:0.01'],
            'date'                    => ['required', 'date'],
            'description'             => ['required', 'string', 'max:255'],
            'notes'                   => ['nullable', 'string', 'max:1000'],
            'is_transfer'             => ['boolean'],
            'to_account_id'           => ['nullable', 'integer', OwnedByAuthenticatedUser::accounts(), 'different:account_id'],
        ];
    }
}
