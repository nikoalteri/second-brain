<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VaultCard;

class VaultCardPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, VaultCard $vaultCard): bool
    {
        return $user->id === $vaultCard->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, VaultCard $vaultCard): bool
    {
        return $user->id === $vaultCard->user_id;
    }

    public function delete(User $user, VaultCard $vaultCard): bool
    {
        return $user->id === $vaultCard->user_id;
    }

    public function restore(User $user, VaultCard $vaultCard): bool
    {
        return $user->id === $vaultCard->user_id;
    }

    public function forceDelete(User $user, VaultCard $vaultCard): bool
    {
        return $user->id === $vaultCard->user_id;
    }
}
