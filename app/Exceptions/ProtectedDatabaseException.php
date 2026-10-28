<?php

namespace App\Exceptions;

use RuntimeException;

class ProtectedDatabaseException extends RuntimeException
{
    public const EXIT_CODE = 1;

    public function __construct(string $message = '')
    {
        parent::__construct($message, self::EXIT_CODE);
    }
}