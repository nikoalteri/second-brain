<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserService
{
    public function updateProfile(User $user, array $data): void
    {
        $user->fill($data);
        if (isset($data['email']) && $user->isDirty('email')) {
            $user->email_verified_at = null;
        }
        $user->save();
    }

    public function deleteAccount(User $user, string $password): void
    {
        // Validazione password già fatta nel controller
        Auth::logout();
        $user->delete();
    }
}
