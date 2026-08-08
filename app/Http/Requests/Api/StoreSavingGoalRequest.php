<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSavingGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            // Ownership of the account is enforced in the controller (scoped lookup), not here:
            // Filament exempts superadmin from HasUserScoping, so its account picker can list
            // accounts across every user — the goal's user_id must follow the CHOSEN account's
            // real owner, not the acting admin.
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'target_amount' => ['required', 'numeric', 'min:0.01'],
            'target_date' => ['nullable', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'archived'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
