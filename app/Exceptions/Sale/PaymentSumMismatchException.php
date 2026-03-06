<?php

namespace App\Exceptions\Sale;

use App\Exceptions\BusinessException;

class PaymentSumMismatchException extends BusinessException
{
    public function __construct()
    {
        parent::__construct(
            'La suma de los pagos no coincide con el total de la orden.',
            'PAYMENT_SUM_MISMATCH',
            422
        );
    }
}
