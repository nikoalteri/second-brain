<?php

namespace App\Policies;

use App\Models\CreditCardPayment;
use App\Models\User;

class CreditCardPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CreditCardPayment $payment): bool
    {
        return $payment->creditCard->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, CreditCardPayment $payment): bool
    {
        return $payment->creditCard->user_id === $user->id;
    }

    public function delete(User $user, CreditCardPayment $payment): bool
    {
        return $payment->creditCard->user_id === $user->id;
    }

    public function restore(User $user, CreditCardPayment $payment): bool
    {
        return $payment->creditCard->user_id === $user->id;
    }

    public function forceDelete(User $user, CreditCardPayment $payment): bool
    {
        return $payment->creditCard->user_id === $user->id;
    }
}
