<?php

namespace App\Http\Requests\Api;

use App\Enums\CreditCardCycleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCreditCardCycleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'period_start_date' => ['sometimes', 'required', 'date'],
            'statement_date' => ['sometimes', 'required', 'date'],
            'due_date' => ['sometimes', 'nullable', 'date'],
            'total_spent' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', 'nullable', Rule::in(array_column(CreditCardCycleStatus::cases(), 'value'))],
        ];
    }
}
