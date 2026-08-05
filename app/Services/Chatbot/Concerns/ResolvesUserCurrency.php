<?php

namespace App\Services\Chatbot\Concerns;

use App\Models\Account;
use App\Models\User;

trait ResolvesUserCurrency
{
    protected function resolveUserCurrency(User $user): string
    {
        return Account::query()
            ->when(
                ! $user->hasRole('superadmin'),
                fn ($query) => $query->where('user_id', $user->id)
            )
            ->where('is_active', true)
            ->orderBy('id')
            ->value('currency') ?? 'EUR';
    }
}
