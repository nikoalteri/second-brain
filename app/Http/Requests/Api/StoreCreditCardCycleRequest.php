<?php

namespace App\Http\Requests\Api;

use App\Enums\CreditCardCycleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditCardCycleRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'period_start_date' => ['required', 'date'],
            'statement_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date'],
            'total_spent' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', Rule::in(array_column(CreditCardCycleStatus::cases(), 'value'))],
        ];
    }
}
