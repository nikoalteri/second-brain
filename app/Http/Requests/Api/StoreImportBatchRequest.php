<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreImportBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Target ownership is enforced by ImportBatchService (imports are personal even for a
     * superadmin), so only the shape is validated here.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['nullable', 'integer', 'required_without:credit_card_id', 'prohibits:credit_card_id'],
            'credit_card_id' => ['nullable', 'integer', 'required_without:account_id'],
            'file' => ['required', 'file', 'extensions:csv,xlsx', 'max:' . config('import.max_file_kb')],
            'statement_ending_balance' => ['nullable', 'numeric', 'between:-9999999999999,9999999999999'],
            'period_start' => ['nullable', 'date'],
            'period_end' => ['nullable', 'date', 'after_or_equal:period_start'],
        ];
    }
}
