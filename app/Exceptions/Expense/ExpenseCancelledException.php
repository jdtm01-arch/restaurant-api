<?php

namespace App\Exceptions\Expense;

use App\Exceptions\BusinessException;

class ExpenseCancelledException extends BusinessException
{
    public function __construct(string $message = 'No se puede registrar pago en gasto cancelado.')
    {
        parent::__construct($message, 'EXPENSE_CANCELLED', 422);
    }
}
