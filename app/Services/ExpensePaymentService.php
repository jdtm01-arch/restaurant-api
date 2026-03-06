<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Exceptions\Expense\ExpenseLockedException;
use App\Exceptions\Expense\ExpenseCancelledException;
use App\Exceptions\Expense\ExpenseAlreadyPaidException;
use App\Exceptions\Expense\PaymentExceedsAmountException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class ExpensePaymentService
{
    protected CashValidationService $cashValidation;
    protected FinancialMovementService $financialMovementService;

    public function __construct(
        CashValidationService $cashValidation,
        FinancialMovementService $financialMovementService
    ) {
        $this->cashValidation = $cashValidation;
        $this->financialMovementService = $financialMovementService;
    }

    /*
    |--------------------------------------------------------------------------
    | REGISTER PAYMENT
    |--------------------------------------------------------------------------
    */

    public function registerPayment(Expense $expense, array $data): ExpensePayment
    {
        return DB::transaction(function () use ($expense, $data) {

            // 1. Validar bloqueo por cierre
            if ($this->cashValidation->isExpenseLocked($expense)) {
                throw new ExpenseLockedException();
            }

            // 2. No permitir pago en gasto cancelado
            if ($expense->status->slug === 'cancelled') {
                throw new ExpenseCancelledException();
            }

            // 3. No permitir pago si ya está marcado como pagado
            if ($expense->status->slug === 'paid') {
                throw new ExpenseAlreadyPaidException();
            }

            // 4. Validar que la fecha de pago no sea anterior a la fecha del gasto
            $paidAt = Carbon::parse($data['paid_at'])->startOfDay();
            $expenseDate = $expense->expense_date->copy()->startOfDay();

            if ($paidAt->lt($expenseDate)) {
                throw ValidationException::withMessages([
                    'paid_at' => ['La fecha de pago no puede ser anterior a la fecha del gasto (' . $expense->expense_date->format('d/m/Y') . ').'],
                ]);
            }

            // 5a. Validar que la fecha de pago no sea anterior o igual al último cierre contable
            $paidAtDate = $paidAt->format('Y-m-d');
            if ($this->cashValidation->isBeforeOrOnLastClosing($expense->restaurant_id, $paidAtDate)) {
                $lastClosing = $this->cashValidation->getLastClosingDate($expense->restaurant_id);
                throw ValidationException::withMessages([
                    'paid_at' => [
                        'La fecha de pago no puede ser anterior o igual al último cierre contable ('
                        . \Carbon\Carbon::parse($lastClosing)->format('d/m/Y') . '). '
                        . 'Solo se pueden registrar pagos posteriores a ese cierre.',
                    ],
                ]);
            }

            // 5b. Validar que exista caja registradora abierta para esa fecha
            if (! $this->cashValidation->canRegisterPaymentOnDate($expense->restaurant_id, $paidAtDate)) {
                throw ValidationException::withMessages([
                    'paid_at' => ['No existe caja registradora abierta para la fecha de pago. Verifique que la caja esté abierta ese día.'],
                ]);
            }

            // 6. Validar que no exceda el monto total
            $currentTotal = $expense->payments()->sum('amount');
            $newTotal = $currentTotal + $data['amount'];

            if ($newTotal > $expense->amount) {
                throw new PaymentExceedsAmountException();
            }

            // 7. Registrar pago
            $payment = ExpensePayment::create([
                'expense_id'           => $expense->id,
                'payment_method_id'    => $data['payment_method_id'],
                'financial_account_id' => $data['financial_account_id'] ?? null,
                'amount'               => $data['amount'],
                'paid_at'              => $data['paid_at'] ?? now(),
            ]);

            // 8. Generar movimiento financiero si hay cuenta asignada
            $this->financialMovementService->createForExpensePayment(
                $payment,
                $expense->restaurant_id,
                Carbon::parse($payment->paid_at)->toDateString()
            );

            return $payment;
        });
    }
}