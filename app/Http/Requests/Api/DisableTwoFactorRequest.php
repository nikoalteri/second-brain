<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DisableTwoFactorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Checked against the authenticated user's hash in the controller, not via the
            // `current_password` rule: that validates against the default session guard, which
            // has no bearing on a stateless Sanctum-token request.
            'password' => ['required', 'string'],
        ];
    }
}
