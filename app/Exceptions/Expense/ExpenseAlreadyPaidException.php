<?php

namespace App\Exceptions\Expense;

use App\Exceptions\BusinessException;

class ExpenseAlreadyPaidException extends BusinessException
{
    public function __construct(string $message = 'El gasto ya está marcado como pagado.')
    {
        parent::__construct($message, 'EXPENSE_ALREADY_PAID', 422);
    }
}
