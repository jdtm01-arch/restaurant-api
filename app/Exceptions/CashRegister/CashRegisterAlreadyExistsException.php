<?php

namespace App\Exceptions\CashRegister;

use App\Exceptions\BusinessException;

class CashRegisterAlreadyExistsException extends BusinessException
{
    public function __construct(string $message = 'Ya existe una caja abierta para este restaurante en esta fecha.')
    {
        parent::__construct($message, 'CASH_REGISTER_ALREADY_EXISTS', 409);
    }
}
