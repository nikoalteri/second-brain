<?php

namespace App\Policies;

use App\Models\TransactionType;
use App\Models\User;

class TransactionTypePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransactionType $model): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('superadmin');
    }

    public function update(User $user, TransactionType $model): bool
    {
        return $user->hasRole('superadmin');
    }

    public function delete(User $user, TransactionType $model): bool
    {
        return $user->hasRole('superadmin');
    }
}
