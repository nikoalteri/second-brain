<?php

namespace App\Policies;

use App\Models\MaintenanceTask;
use App\Models\User;

class MaintenanceTaskPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, MaintenanceTask $task): bool
    {
        return $task->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, MaintenanceTask $task): bool
    {
        return $task->user_id === $user->id;
    }

    public function delete(User $user, MaintenanceTask $task): bool
    {
        return $task->user_id === $user->id;
    }

    public function restore(User $user, MaintenanceTask $task): bool
    {
        return $task->user_id === $user->id;
    }

    public function forceDelete(User $user, MaintenanceTask $task): bool
    {
        return $task->user_id === $user->id;
    }
}
