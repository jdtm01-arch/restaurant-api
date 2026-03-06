<?php

namespace App\Exceptions\Sale;

use App\Exceptions\BusinessException;

class OrderNotClosedException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'La orden no está cerrada. Debe cerrarse antes de generar la venta.',
            'ORDER_NOT_CLOSED',
            422
        );
    }
}
