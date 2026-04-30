<?php

namespace App\Http\Requests\Api;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'first_name' => $this->normalizeString($this->input('first_name')),
            'last_name' => $this->normalizeString($this->input('last_name')),
            'email' => filled($this->input('email')) ? Str::lower(trim((string) $this->input('email'))) : null,
            'phone_country_code' => PhoneNumber::normalizeCountryCode($this->input('phone_country_code')),
            'phone_number' => $this->normalizeString($this->input('phone_number')),
            'phone' => PhoneNumber::combine($this->input('phone_country_code'), $this->input('phone_number') ?? $this->input('phone')),
            'tax_code' => filled($this->input('tax_code')) ? strtoupper(str_replace(' ', '', trim((string) $this->input('tax_code')))) : null,
        ]);
    }

    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'phone_country_code' => ['nullable', 'string', Rule::in(array_keys(PhoneNumber::countryCodeOptions()))],
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\s().-]+$/'],
            'phone' => ['nullable', 'string', 'max:25'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'tax_code' => ['nullable', 'string', 'size:16', 'regex:/^[A-Z0-9]{16}$/', Rule::unique('users', 'tax_code')->ignore($userId)],
        ];
    }

    private function normalizeString(mixed $value): ?string
    {
        return filled($value) ? trim((string) $value) : null;
    }
}
