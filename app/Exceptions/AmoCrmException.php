<?php

namespace App\Exceptions;

use Exception;

class AmoCrmException extends Exception
{
    public function __construct(
        string $message = '',
        private readonly int $statusCode = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function isNotFound(): bool
    {
        return $this->statusCode === 404
            || str_contains(mb_strtolower($this->message), 'is not found')
            || str_contains(mb_strtolower($this->message), 'not found');
    }
}
