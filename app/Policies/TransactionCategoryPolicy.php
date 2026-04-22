<?php

namespace App\Policies;

use App\Models\User;
use App\Models\TransactionCategory;
use Illuminate\Auth\Access\Response;

class TransactionCategoryPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        return $user->hasRole('superadmin') ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TransactionCategory $category): bool
    {
        return $category->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, TransactionCategory $category): bool
    {
        return $category->user_id === $user->id;
    }

    public function delete(User $user, TransactionCategory $category): bool
    {
        return $category->user_id === $user->id;
    }
}
