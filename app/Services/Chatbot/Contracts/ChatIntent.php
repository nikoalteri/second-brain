<?php

namespace App\Services\Chatbot\Contracts;

use App\Models\User;

interface ChatIntent
{
    /**
     * The allow-listed intent key this handler answers.
     */
    public function key(): string;

    /**
     * Answer the intent for the given user.
     *
     * @param  array<string, mixed>  $params
     * @return array{
     *     intent: string,
     *     headline: string,
     *     highlight: null|array{label: string, value: float, currency: string},
     *     items: array<int, array{label: string, value: float, currency: string, detail: string|null}>,
     *     empty_message: string|null
     * }
     */
    public function handle(User $user, array $params): array;
}
