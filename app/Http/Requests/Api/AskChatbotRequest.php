<?php

namespace App\Http\Requests\Api;

use App\Services\Chatbot\IntentRouter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AskChatbotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'intent' => ['required', 'string', Rule::in(IntentRouter::SUPPORTED_INTENTS)],
            'params' => ['sometimes', 'array'],
            'params.days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'params.month' => ['sometimes', 'string', 'date_format:Y-m'],
        ];
    }

    public function messages(): array
    {
        return [
            'intent.in' => 'I can only help with balances, upcoming payments, and monthly spending right now.',
            'intent.required' => 'I can only help with balances, upcoming payments, and monthly spending right now.',
        ];
    }
}
