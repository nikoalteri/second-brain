<?php

namespace App\Policies;

use App\Models\CreditCardCycle;
use App\Models\User;

class CreditCardCyclePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CreditCardCycle $cycle): bool
    {
        return $cycle->creditCard->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CreditCardCycle $cycle): bool
    {
        return $cycle->creditCard->user_id === $user->id;
    }

    public function delete(User $user, CreditCardCycle $cycle): bool
    {
        return $cycle->creditCard->user_id === $user->id;
    }

    public function restore(User $user, CreditCardCycle $cycle): bool
    {
        return $cycle->creditCard->user_id === $user->id;
    }

    public function forceDelete(User $user, CreditCardCycle $cycle): bool
    {
        return $cycle->creditCard->user_id === $user->id;
    }
}
