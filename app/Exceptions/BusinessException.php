<?php

namespace App\Exceptions;

use Exception;

class BusinessException extends Exception
{
    protected int $httpCode;
    protected string $internalCode;

    public function __construct(
        string $message = 'Error de negocio',
        string $internalCode = 'BUSINESS_ERROR',
        int $httpCode = 400
    ) {
        parent::__construct($message);
        $this->httpCode = $httpCode;
        $this->internalCode = $internalCode;
    }

    public function getHttpCode(): int
    {
        return $this->httpCode;
    }

    public function getInternalCode(): string
    {
        return $this->internalCode;
    }

    public function render()
    {
        return response()->json([
            'success' => false,
            'error' => [
                'message' => $this->getMessage(),
                'code' => $this->internalCode,
            ],
        ], $this->httpCode);
    }
}
