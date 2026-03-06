<?php

namespace App\Exceptions\Expense;

use App\Exceptions\BusinessException;

class ExpenseLockedException extends BusinessException
{
    public function __construct(string $message = 'Gasto bloqueado por cierre de caja.')
    {
        parent::__construct($message, 'EXPENSE_LOCKED', 422);
    }
}
