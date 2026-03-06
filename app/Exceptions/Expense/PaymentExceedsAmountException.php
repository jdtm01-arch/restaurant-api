<?php

namespace App\Exceptions\Expense;

use App\Exceptions\BusinessException;

class PaymentExceedsAmountException extends BusinessException
{
    public function __construct(string $message = 'El monto excede el total del gasto.')
    {
        parent::__construct($message, 'PAYMENT_EXCEEDS_AMOUNT', 422);
    }
}
