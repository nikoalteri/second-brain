<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSavingGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'target_amount' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'target_date' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', Rule::in(['active', 'achieved', 'archived'])],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
