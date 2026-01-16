<?php

namespace App\Shared\Exceptions;

use Exception;

class HttpException extends Exception
{
    private int $status;
    private string $errorCode;
    private array $details;

    public function __construct(int $status, string $errorCode, string $message, array $details = [])
    {
        parent::__construct($message);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->details = $details;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getDetails(): array
    {
        return $this->details;
    }
}
