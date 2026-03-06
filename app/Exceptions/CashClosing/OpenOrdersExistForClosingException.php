<?php

namespace App\Exceptions\CashClosing;

use App\Exceptions\BusinessException;

class OpenOrdersExistForClosingException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'Existen órdenes abiertas. Ciérrelas o cancélelas antes del cierre contable.',
            'OPEN_ORDERS_EXIST_FOR_CLOSING',
            422
        );
    }
}
