<?php

namespace App\Http\Requests\Api;

use App\Services\Import\ImportRowNormalizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MapImportBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Column indexes are 0-based positions in the file's rows. Parsing options are explicit so the
     * result never depends on the server locale.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $column = ['integer', 'min:0', 'max:' . (config('import.max_columns') - 1)];

        return [
            'header_row' => ['nullable', 'integer', 'min:0', 'max:50'],
            'date_column' => ['required', ...$column],
            'date_format' => ['required', Rule::in(ImportRowNormalizer::DATE_FORMATS)],
            'amount_mode' => ['required', Rule::in(['single', 'split'])],
            'amount_column' => ['required_if:amount_mode,single', 'nullable', ...$column],
            'debit_column' => ['required_if:amount_mode,split', 'nullable', ...$column],
            'credit_column' => ['required_if:amount_mode,split', 'nullable', ...$column, 'different:debit_column'],
            'decimal_separator' => ['required', Rule::in(['.', ','])],
            'thousands_separator' => ['present', 'nullable', Rule::in(['', '.', ',', ' ', "'"]), 'different:decimal_separator'],
            'invert_sign' => ['nullable', 'boolean'],
            'description_column' => ['required', ...$column],
            'category_column' => ['nullable', ...$column],
            'currency_column' => ['nullable', ...$column],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapping(): array
    {
        $data = $this->validated();

        return [
            'header_row' => (int) ($data['header_row'] ?? 0),
            'date_column' => (int) $data['date_column'],
            'date_format' => $data['date_format'],
            'amount_mode' => $data['amount_mode'],
            'amount_column' => isset($data['amount_column']) ? (int) $data['amount_column'] : null,
            'debit_column' => isset($data['debit_column']) ? (int) $data['debit_column'] : null,
            'credit_column' => isset($data['credit_column']) ? (int) $data['credit_column'] : null,
            'decimal_separator' => $data['decimal_separator'],
            'thousands_separator' => (string) ($data['thousands_separator'] ?? ''),
            'invert_sign' => (bool) ($data['invert_sign'] ?? false),
            'description_column' => (int) $data['description_column'],
            'category_column' => isset($data['category_column']) ? (int) $data['category_column'] : null,
            'currency_column' => isset($data['currency_column']) ? (int) $data['currency_column'] : null,
        ];
    }
}
