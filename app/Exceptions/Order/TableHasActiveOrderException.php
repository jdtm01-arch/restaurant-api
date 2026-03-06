<?php

namespace App\Exceptions\Order;

use App\Exceptions\BusinessException;

class TableHasActiveOrderException extends BusinessException
{
    public function __construct(string $message = 'La mesa ya tiene una orden abierta.')
    {
        parent::__construct($message, 'TABLE_HAS_ACTIVE_ORDER', 409);
    }
}
