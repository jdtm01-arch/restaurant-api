<?php

namespace App\Exceptions\Order;

use App\Exceptions\BusinessException;

class OrderNotOpenException extends BusinessException
{
    public function __construct(string $message = 'La orden no está abierta.')
    {
        parent::__construct($message, 'ORDER_NOT_OPEN', 422);
    }
}
