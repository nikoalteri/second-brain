<?php

namespace App\Policies;

use App\Models\LoanPayment;
use App\Models\User;

class LoanPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LoanPayment $payment): bool
    {
        return $payment->loan->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, LoanPayment $payment): bool
    {
        return $payment->loan->user_id === $user->id;
    }

    public function delete(User $user, LoanPayment $payment): bool
    {
        return $payment->loan->user_id === $user->id;
    }

    public function restore(User $user, LoanPayment $payment): bool
    {
        return $payment->loan->user_id === $user->id;
    }

    public function forceDelete(User $user, LoanPayment $payment): bool
    {
        return $payment->loan->user_id === $user->id;
    }
}
