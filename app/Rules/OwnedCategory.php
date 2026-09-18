<?php

namespace App\Rules;

/**
 * No-argument variant so it can be referenced by class name from GraphQL @rules.
 */
class OwnedCategory extends OwnedByAuthenticatedUser
{
    public function __construct()
    {
        parent::__construct('transaction_categories');
    }
}
