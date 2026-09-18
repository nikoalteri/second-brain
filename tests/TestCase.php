<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The admin panel requires a second factor from everyone who can open it. Requests made
     * with actingAs() skip the login challenge, but not the "set up MFA first" redirect, so a
     * user who opens panel pages over HTTP needs it enabled. The secret is a synthetic test value.
     */
    protected function withPanelMfa(User $user): User
    {
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();

        return $user;
    }
}
