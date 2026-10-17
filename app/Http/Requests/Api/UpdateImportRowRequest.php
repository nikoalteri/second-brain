<?php

namespace App\Http\Requests\Api;

use App\Models\TransactionCategory;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class UpdateImportRowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'description' => ['sometimes', 'nullable', 'string', 'max:255'],
            'amount' => ['sometimes', 'required', 'numeric', 'not_in:0', 'between:-9999999999999,9999999999999', 'regex:/^-?\d+(\.\d{1,2})?$/'],
            'date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            // Own, plain (non-subscription) categories only: the same set the mapping can match.
            'category_id' => ['sometimes', 'nullable', 'integer', function (string $attribute, mixed $value, Closure $fail) {
                $owned = $value === null || TransactionCategory::query()->withoutUserScope()
                    ->where('user_id', $this->user()->id)
                    ->where('scope', 'generic')
                    ->whereKey($value)
                    ->exists();

                if (! $owned) {
                    $fail('The selected category is invalid.');
                }
            }],
            'status' => ['sometimes', 'in:draft,discarded'],
        ];
    }
}
