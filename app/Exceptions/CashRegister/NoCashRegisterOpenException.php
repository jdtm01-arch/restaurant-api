<?php

namespace App\Exceptions\CashRegister;

use App\Exceptions\BusinessException;

class NoCashRegisterOpenException extends BusinessException
{
    public function __construct(string $message = 'No hay caja abierta para este restaurante.')
    {
        parent::__construct($message, 'NO_CASH_REGISTER_OPEN', 422);
    }
}
