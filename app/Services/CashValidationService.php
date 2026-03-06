<?php

namespace App\Services;

use App\Models\CashClosing;
use App\Models\CashRegister;
use App\Models\Expense;

class CashValidationService
{
    /**
     * Verifica si existe cierre para una fecha específica.
     */
    public function hasClosing(int $restaurantId, string $date): bool
    {
        return CashClosing::where('restaurant_id', $restaurantId)
            ->whereDate('date', $date)
            ->exists();
    }

    /**
     * Determina si un gasto está congelado.
     */
    public function isExpenseLocked(Expense $expense): bool
    {
        $hasClosing = $this->hasClosing(
            $expense->restaurant_id,
            $expense->expense_date->format('Y-m-d')
        );

        if (! $hasClosing) {
            return false;
        }

        // Si hay cierre y está paid o cancelled → bloqueado
        return in_array($expense->status->slug, ['paid', 'cancelled']);
    }

    /**
     * Verifica si se puede cambiar a estado paid.
     * Condición: sin CashClosing para esa fecha Y sin CashRegister cerrado.
     */
    public function canMarkAsPaid(Expense $expense): bool
    {
        $date = $expense->expense_date->format('Y-m-d');
        $restaurantId = $expense->restaurant_id;

        if ($this->hasClosing($restaurantId, $date)) {
            return false;
        }

        // Bloquear si la caja del restaurante para esa fecha está cerrada
        $register = CashRegister::where('restaurant_id', $restaurantId)
            ->whereDate('date', $date)
            ->first();

        if ($register && $register->isClosed()) {
            return false;
        }

        return true;
    }

    /**
     * Verifica si se puede registrar un pago en una fecha específica.
     * Requiere: sin cierre contable, registro de caja existente y abierto.
     */
    public function canRegisterPaymentOnDate(int $restaurantId, string $date): bool
    {
        if ($this->hasClosing($restaurantId, $date)) {
            return false;
        }

        $register = CashRegister::where('restaurant_id', $restaurantId)
            ->whereDate('date', $date)
            ->first();

        // Debe existir un registro físico de caja para esa fecha
        if (! $register) {
            return false;
        }

        if ($register->isClosed()) {
            return false;
        }

        return true;
    }

    /**
     * Retorna la fecha del último cierre contable del restaurante, o null si no hay ninguno.
     */
    public function getLastClosingDate(int $restaurantId): ?string
    {
        return CashClosing::where('restaurant_id', $restaurantId)
            ->orderByDesc('date')
            ->value('date');
    }

    /**
     * Verifica si una fecha es anterior o igual al último cierre contable.
     * Si no hay cierres contables, retorna false (sin restricción).
     */
    public function isBeforeOrOnLastClosing(int $restaurantId, string $date): bool
    {
        $lastClosing = $this->getLastClosingDate($restaurantId);

        if ($lastClosing === null) {
            return false;
        }

        return $date <= $lastClosing;
    }
}