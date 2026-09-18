<?php

namespace App\Http\Requests\Api;

use App\Rules\OwnedByAuthenticatedUser;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'account_id'              => ['sometimes', 'required', 'integer', OwnedByAuthenticatedUser::accounts()],
            'transaction_type_id'     => ['sometimes', 'required', 'integer', 'exists:transaction_types,id'],
            'transaction_category_id' => ['sometimes', 'nullable', 'integer', OwnedByAuthenticatedUser::categories()],
            'amount'                  => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'date'                    => ['sometimes', 'required', 'date'],
            'description'             => ['sometimes', 'required', 'string', 'max:255'],
            'notes'                   => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
