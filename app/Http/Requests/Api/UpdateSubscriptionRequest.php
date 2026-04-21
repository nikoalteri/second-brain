<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSubscriptionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name'              => ['sometimes', 'required', 'string', 'max:255'],
            'monthly_cost'      => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'annual_cost'       => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'frequency'         => ['sometimes', 'required', Rule::in(['monthly', 'annual', 'biennial'])],
            'day_of_month'      => ['sometimes', 'required', 'integer', 'min:1', 'max:31'],
            'next_renewal_date' => ['sometimes', 'required', 'date'],
            'status'            => ['sometimes', 'required', Rule::in(['active', 'inactive', 'cancelled'])],
            'notes'             => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
