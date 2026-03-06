<?php

namespace App\Exceptions\CashClosing;

use App\Exceptions\BusinessException;

class CashRegisterNotClosedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'La caja registradora del día debe estar cerrada para realizar el cierre contable.',
            'CASH_REGISTER_NOT_CLOSED',
            422
        );
    }
}
