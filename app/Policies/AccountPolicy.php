<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('finance.accounts.viewAny');
    }

    public function view(User $user, Account $account): Response
    {
        return $user->id === $account->user_id
            ? Response::allow()
            : Response::deny('Not your account.');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('finance.accounts.create');
    }

    public function update(User $user, Account $account): Response
    {
        return $user->id === $account->user_id
            ? Response::allow()
            : Response::deny('Not your account.');
    }

    public function delete(User $user, Account $account): Response
    {
        return $user->id === $account->user_id && !$account->transactions()->exists()
            ? Response::allow()
            : Response::deny('You have transactions on this account.');
    }
}
