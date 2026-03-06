<?php

namespace App\Exceptions\Expense;

use App\Exceptions\BusinessException;

class ExpenseCannotBeDeletedPaidException extends BusinessException
{
    public function __construct(string $message = 'No se puede eliminar un gasto pagado.')
    {
        parent::__construct($message, 'EXPENSE_CANNOT_DELETE_PAID', 422);
    }
}
