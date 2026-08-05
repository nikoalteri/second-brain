<?php

namespace App\Services\Chatbot\Exceptions;

use Exception;

class UnsupportedIntentException extends Exception
{
    public function __construct(private readonly string $intentKey)
    {
        parent::__construct('I can only help with balances, upcoming payments, and monthly spending right now.');
    }

    public function intentKey(): string
    {
        return $this->intentKey;
    }
}
