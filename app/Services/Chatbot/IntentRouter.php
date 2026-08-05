<?php

namespace App\Services\Chatbot;

use App\Models\User;
use App\Services\Chatbot\Contracts\ChatIntent;
use App\Services\Chatbot\Exceptions\UnsupportedIntentException;
use Illuminate\Support\Collection;

class IntentRouter
{
    /**
     * The only intent keys this phase may answer (D-01 / D-07 confidence boundary).
     *
     * @var array<int, string>
     */
    public const SUPPORTED_INTENTS = [
        'account_balances',
        'upcoming_payments',
        'monthly_spending',
    ];

    /** @var Collection<string, ChatIntent> */
    private Collection $intents;

    /**
     * @param  array<int, ChatIntent>  $intents
     */
    public function __construct(array $intents)
    {
        $this->intents = collect($intents)->keyBy(fn (ChatIntent $intent) => $intent->key());
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws UnsupportedIntentException
     */
    public function route(User $user, string $intentKey, array $params = []): array
    {
        if (! in_array($intentKey, self::SUPPORTED_INTENTS, true)) {
            throw new UnsupportedIntentException($intentKey);
        }

        $intent = $this->intents->get($intentKey);

        if (! $intent instanceof ChatIntent) {
            throw new UnsupportedIntentException($intentKey);
        }

        return $intent->handle($user, $params);
    }
}
