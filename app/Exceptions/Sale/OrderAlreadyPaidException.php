<?php

namespace App\Exceptions\Sale;

use App\Exceptions\BusinessException;

class OrderAlreadyPaidException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'Esta orden ya fue pagada y tiene una venta asociada.',
            'ORDER_ALREADY_PAID',
            409
        );
    }
}
