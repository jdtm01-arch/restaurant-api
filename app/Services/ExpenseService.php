<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\ExpenseStatus;
use App\Exceptions\Expense\ExpenseLockedException;
use App\Exceptions\Expense\ExpenseCannotBeDeletedPaidException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class ExpenseService
{
    protected CashValidationService $cashValidation;
    protected ExpenseAuditService $auditService;

    public function __construct(
        CashValidationService $cashValidation,
        ExpenseAuditService $auditService
    ) {
        $this->cashValidation = $cashValidation;
        $this->auditService = $auditService;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */

    public function create(array $data): Expense
    {
        return DB::transaction(function () use ($data) {

            // Validar que la fecha no sea anterior o igual al último cierre contable
            if ($this->cashValidation->isBeforeOrOnLastClosing($data['restaurant_id'], $data['expense_date'])) {
                $lastClosing = $this->cashValidation->getLastClosingDate($data['restaurant_id']);
                throw ValidationException::withMessages([
                    'expense_date' => [
                        'La fecha del gasto no puede ser anterior o igual al último cierre contable ('
                        . \Carbon\Carbon::parse($lastClosing)->format('d/m/Y') . '). '
                        . 'Solo se pueden registrar gastos posteriores a ese cierre.',
                    ],
                ]);
            }

            // Validar que exista caja abierta para esa fecha
            if (! $this->cashValidation->canRegisterPaymentOnDate($data['restaurant_id'], $data['expense_date'])) {
                throw ValidationException::withMessages([
                    'expense_date' => ['No existe caja registradora abierta para esa fecha. No se puede registrar el gasto.'],
                ]);
            }

            $expense = Expense::create([
                'restaurant_id'       => $data['restaurant_id'],
                'supplier_id'         => $data['supplier_id'] ?? null,
                'expense_category_id' => $data['expense_category_id'],
                'expense_status_id'   => $data['expense_status_id'],
                'user_id'             => Auth::id(),
                'amount'              => $data['amount'],
                'description'         => $data['description'],
                'expense_date'        => $data['expense_date'],
                'paid_at'             => $this->resolvePaidAt($data),
            ]);

            $this->auditService->log(
                $expense,
                [],
                $expense->toArray()
            );

            return $expense;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */

    public function update(Expense $expense, array $data): Expense
    {
        return DB::transaction(function () use ($expense, $data) {

            if ($this->cashValidation->isExpenseLocked($expense)) {
                throw new ExpenseLockedException();
            }

            // Si la fecha del gasto cambia, validar contra el último cierre contable
            $newExpenseDate = $data['expense_date'] ?? $expense->expense_date->toDateString();
            if ($newExpenseDate !== $expense->expense_date->toDateString()) {
                if ($this->cashValidation->isBeforeOrOnLastClosing($expense->restaurant_id, $newExpenseDate)) {
                    $lastClosing = $this->cashValidation->getLastClosingDate($expense->restaurant_id);
                    throw ValidationException::withMessages([
                        'expense_date' => [
                            'La nueva fecha no puede ser anterior o igual al último cierre contable ('
                            . \Carbon\Carbon::parse($lastClosing)->format('d/m/Y') . ').',
                        ],
                    ]);
                }
            }

            $oldValues = $expense->getOriginal();
            $originalStatusId = $expense->expense_status_id;
            $newStatusId = $data['expense_status_id'];

            /*
            |--------------------------------------------------------------------------
            | Validación de cambio a PAID
            |--------------------------------------------------------------------------
            */

            if ($originalStatusId != $newStatusId) {

                $newStatus = ExpenseStatus::findOrFail($newStatusId);

                if ($newStatus->slug === 'paid') {

                    // 1. Validar cierre
                    if (! $this->cashValidation->canMarkAsPaid($expense)) {
                        throw new ExpenseLockedException(
                            'Existe cierre de caja en esa fecha. Cambie la fecha antes de marcar como pagado.'
                        );
                    }

                    // 2. Validar existencia de pagos
                    $totalPayments = $expense->payments()->sum('amount');

                    if ($totalPayments <= 0) {
                        throw ValidationException::withMessages([
                            'payments' => 'No existen pagos registrados para este gasto.'
                        ]);
                    }

                    // 3. Validar sumatoria exacta
                    if ((float) $totalPayments !== (float) $expense->amount) {
                        throw ValidationException::withMessages([
                            'amount' => ['La suma de pagos debe ser exactamente igual al monto del gasto.']
                        ]);
                    }

                    // 4. Asignar fecha de pago
                    $data['paid_at'] = now();

                } else {

                    // Si deja de estar en paid, limpiar fecha
                    $data['paid_at'] = null;
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Update principal
            |--------------------------------------------------------------------------
            */

            $expense->update([
                'supplier_id'         => $data['supplier_id'] ?? null,
                'expense_category_id' => $data['expense_category_id'],
                'expense_status_id'   => $newStatusId,
                'amount'              => $data['amount'],
                'description'         => $data['description'],
                'expense_date'        => $data['expense_date'],
                'paid_at'             => $data['paid_at'] ?? $expense->paid_at,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Auditoría
            |--------------------------------------------------------------------------
            */

            $action = $originalStatusId != $newStatusId
                ? 'status_changed'
                : 'updated';

            $this->auditService->log(
                $expense,
                $oldValues,
                $expense->fresh()->toArray()
            );

            return $expense;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE (SOFT)
    |--------------------------------------------------------------------------
    */

    public function delete(Expense $expense): void
    {
        if ($expense->status->slug === 'paid') {
            throw new ExpenseCannotBeDeletedPaidException();
        }

        if ($this->cashValidation->isExpenseLocked($expense)) {
            throw new ExpenseLockedException('No se puede eliminar. Existe cierre de caja.');
        }

        $expense->delete();
            $this->auditService->log(
            $expense,
            $expense->toArray(),
            []
        );
    }

    /*
    |--------------------------------------------------------------------------
    | STATUS CHANGE - NOT USED
    |--------------------------------------------------------------------------
    */
    /*
    public function changeStatus(Expense $expense, int $newStatusId): Expense
    {
        return DB::transaction(function () use ($expense, $newStatusId) {

            $newStatus = ExpenseStatus::findOrFail($newStatusId);

            if ($newStatus->slug === 'paid') {

                // 1. Validar cierre
                if (! $this->cashValidation->canMarkAsPaid($expense)) {
                    throw new \Exception(
                        'Existe cierre de caja en esa fecha. Cambie la fecha antes de marcar como pagado.'
                    );
                }

                // 2. Validar existencia de pagos
                $totalPayments = $expense->payments()->sum('amount');

                if ($totalPayments <= 0) {
                    throw new \Exception('No existen pagos registrados para este gasto.');
                }

                // 3. Validar sumatoria exacta
                if ((float) $totalPayments !== (float) $expense->amount) {
                    throw new \Exception(
                        'La suma de pagos debe ser exactamente igual al monto del gasto.'
                    );
                }
            }

            $old = $expense->getOriginal();

            $expense->update([
                'expense_status_id' => $newStatusId,
                'paid_at' => $newStatus->slug === 'paid'
                    ? Carbon::now()
                    : null
            ]);

            $this->auditService->log(
                $expense,
                'status_changed',
                $old,
                $expense->fresh()->toArray()
            );

            return $expense;
        });
    }*/

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    protected function resolvePaidAt(array $data): ?string
    {
        $status = ExpenseStatus::find($data['expense_status_id']);

        return $status && $status->slug === 'paid'
            ? Carbon::now()
            : null;
    }
}