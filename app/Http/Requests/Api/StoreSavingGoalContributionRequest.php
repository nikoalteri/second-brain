<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreSavingGoalContributionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Signed: positive deposits toward the goal, negative withdraws from it.
            'amount' => ['required', 'numeric', 'not_in:0'],
            'date' => ['required', 'date'],
            'account_id' => ['nullable', 'integer', 'exists:accounts,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
