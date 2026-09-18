<?php

namespace App\Rules;

/**
 * No-argument variant so it can be referenced by class name from GraphQL @rules.
 */
class OwnedAccount extends OwnedByAuthenticatedUser
{
    public function __construct()
    {
        parent::__construct('accounts', softDeletes: true);
    }
}
