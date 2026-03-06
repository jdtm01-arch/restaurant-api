<?php

namespace App\Exceptions\CashRegister;

use App\Exceptions\BusinessException;

class CashRegisterAlreadyClosedException extends BusinessException
{
    public function __construct(string $message = 'La caja ya está cerrada.')
    {
        parent::__construct($message, 'CASH_REGISTER_ALREADY_CLOSED', 422);
    }
}
