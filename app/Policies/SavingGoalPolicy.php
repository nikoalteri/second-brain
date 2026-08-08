<?php

namespace App\Policies;

use App\Models\SavingGoal;
use App\Models\User;

class SavingGoalPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SavingGoal $savingGoal): bool
    {
        return $user->id === $savingGoal->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, SavingGoal $savingGoal): bool
    {
        return $user->id === $savingGoal->user_id;
    }

    public function delete(User $user, SavingGoal $savingGoal): bool
    {
        return $user->id === $savingGoal->user_id;
    }

    public function restore(User $user, SavingGoal $savingGoal): bool
    {
        return $user->id === $savingGoal->user_id;
    }

    public function forceDelete(User $user, SavingGoal $savingGoal): bool
    {
        return $user->id === $savingGoal->user_id;
    }
}
