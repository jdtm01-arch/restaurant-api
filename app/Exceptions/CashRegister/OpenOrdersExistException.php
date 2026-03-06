<?php

namespace App\Exceptions\CashRegister;

use App\Exceptions\BusinessException;

class OpenOrdersExistException extends BusinessException
{
    public function __construct(string $message = 'Existen órdenes abiertas. Cierre o cancele todas las órdenes antes de cerrar la caja.')
    {
        parent::__construct($message, 'OPEN_ORDERS_EXIST', 422);
    }
}
