<?php

namespace App\Services\Chatbot\Intents;

use App\Models\Account;
use App\Models\User;
use App\Services\Chatbot\Concerns\ResolvesUserCurrency;
use App\Services\Chatbot\Contracts\ChatIntent;

class AccountBalancesIntent implements ChatIntent
{
    use ResolvesUserCurrency;

    public function key(): string
    {
        return 'account_balances';
    }

    public function handle(User $user, array $params): array
    {
        $accounts = Account::query()
            ->when(
                ! $user->hasRole('superadmin'),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'balance', 'currency']);

        $currency = $this->resolveUserCurrency($user);

        $items = $accounts->map(fn (Account $account) => [
            'label' => (string) $account->name,
            'value' => round((float) $account->balance, 2),
            'currency' => (string) ($account->currency ?: $currency),
            'detail' => ucfirst(str_replace('_', ' ', (string) $account->type)),
        ])->values()->all();

        return [
            'intent' => $this->key(),
            'headline' => 'Here are your account balances.',
            'highlight' => $items === [] ? null : [
                'label' => 'Total',
                'value' => round((float) $accounts->sum('balance'), 2),
                'currency' => $currency,
            ],
            'items' => $items,
            'empty_message' => $items === [] ? "You don't have any active accounts yet." : null,
        ];
    }
}
