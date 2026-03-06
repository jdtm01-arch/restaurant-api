<?php

namespace App\Exceptions\Order;

use App\Exceptions\BusinessException;

class OrderNotClosedException extends BusinessException
{
    public function __construct(string $message = 'La orden no está cerrada.')
    {
        parent::__construct($message, 'ORDER_NOT_CLOSED', 422);
    }
}
