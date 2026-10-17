<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmImportBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Either an explicit list of rows (which may include flagged ones: the owner decided), or
     * `all_ready`, which only takes rows with no duplicate or review flag.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'row_ids' => ['required_without:all_ready', 'array', 'min:1', 'max:500'],
            'row_ids.*' => ['integer', 'distinct'],
            // row id => the `review_token` the client received with that row: what the owner actually saw.
            'reviewed' => ['required_with:row_ids', 'array', 'max:500'],
            'reviewed.*' => ['string', 'size:64'],
            'all_ready' => ['required_without:row_ids', 'boolean'],
        ];
    }
}
