<?php

namespace App\Exceptions\Order;

use App\Exceptions\BusinessException;

class EmptyOrderException extends BusinessException
{
    public function __construct(string $message = 'La orden no tiene items.')
    {
        parent::__construct($message, 'EMPTY_ORDER', 422);
    }
}
