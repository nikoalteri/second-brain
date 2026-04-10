<?php

namespace App\Policies;

use App\Models\Trip;
use App\Models\User;

class TripPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Trip $trip): bool
    {
        return $user->id === $trip->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Trip $trip): bool
    {
        return $user->id === $trip->user_id;
    }

    public function delete(User $user, Trip $trip): bool
    {
        return $user->id === $trip->user_id;
    }

    public function restore(User $user, Trip $trip): bool
    {
        return $user->id === $trip->user_id;
    }

    public function forceDelete(User $user, Trip $trip): bool
    {
        return $user->id === $trip->user_id;
    }
}
