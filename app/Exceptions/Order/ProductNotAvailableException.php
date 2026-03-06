<?php

namespace App\Exceptions\Order;

use App\Exceptions\BusinessException;

class ProductNotAvailableException extends BusinessException
{
    public function __construct(string $message = 'El producto no está disponible.')
    {
        parent::__construct($message, 'PRODUCT_NOT_AVAILABLE', 422);
    }
}
