<?php

namespace App\Services;

use App\Models\FinancialMovement;
use App\Models\SalePayment;
use App\Models\ExpensePayment;
use App\Models\AccountTransfer;
use Illuminate\Support\Facades\Auth;

class FinancialMovementService
{
    /*
    |--------------------------------------------------------------------------
    | CREAR MOVIMIENTO POR VENTA
    |--------------------------------------------------------------------------
    | Cada SalePayment con financial_account_id genera un income.
    */

    public function createForSalePayment(SalePayment $salePayment, int $restaurantId, string $date): ?FinancialMovement
    {
        if (! $salePayment->financial_account_id) {
            return null;
        }

        return FinancialMovement::create([
            'restaurant_id'        => $restaurantId,
            'financial_account_id' => $salePayment->financial_account_id,
            'type'                 => FinancialMovement::TYPE_INCOME,
            'reference_type'       => FinancialMovement::REF_SALE_PAYMENT,
            'reference_id'         => $salePayment->id,
            'amount'               => $salePayment->amount,
            'description'          => "Venta — pago #{$salePayment->id}",
            'movement_date'        => $date,
            'created_by'           => Auth::id(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREAR MOVIMIENTO POR GASTO
    |--------------------------------------------------------------------------
    | Cada ExpensePayment con financial_account_id genera un expense.
    */

    public function createForExpensePayment(ExpensePayment $expensePayment, int $restaurantId, string $date): ?FinancialMovement
    {
        if (! $expensePayment->financial_account_id) {
            return null;
        }

        return FinancialMovement::create([
            'restaurant_id'        => $restaurantId,
            'financial_account_id' => $expensePayment->financial_account_id,
            'type'                 => FinancialMovement::TYPE_EXPENSE,
            'reference_type'       => FinancialMovement::REF_EXPENSE_PAYMENT,
            'reference_id'         => $expensePayment->id,
            'amount'               => $expensePayment->amount,
            'description'          => "Gasto — pago #{$expensePayment->id}",
            'movement_date'        => $date,
            'created_by'           => Auth::id(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREAR PAR DE MOVIMIENTOS POR TRANSFERENCIA
    |--------------------------------------------------------------------------
    */

    public function createForTransfer(AccountTransfer $transfer, int $restaurantId): array
    {
        $out = FinancialMovement::create([
            'restaurant_id'        => $restaurantId,
            'financial_account_id' => $transfer->from_account_id,
            'type'                 => FinancialMovement::TYPE_TRANSFER_OUT,
            'reference_type'       => FinancialMovement::REF_TRANSFER,
            'reference_id'         => $transfer->id,
            'amount'               => $transfer->amount,
            'description'          => $transfer->description ?? "Transferencia #{$transfer->id}",
            'movement_date'        => $transfer->created_at->toDateString(),
            'created_by'           => $transfer->created_by,
        ]);

        $in = FinancialMovement::create([
            'restaurant_id'        => $restaurantId,
            'financial_account_id' => $transfer->to_account_id,
            'type'                 => FinancialMovement::TYPE_TRANSFER_IN,
            'reference_type'       => FinancialMovement::REF_TRANSFER,
            'reference_id'         => $transfer->id,
            'amount'               => $transfer->amount,
            'description'          => $transfer->description ?? "Transferencia #{$transfer->id}",
            'movement_date'        => $transfer->created_at->toDateString(),
            'created_by'           => $transfer->created_by,
        ]);

        return [$out, $in];
    }

    /*
    |--------------------------------------------------------------------------
    | LISTAR MOVIMIENTOS
    |--------------------------------------------------------------------------
    */

    public function list(int $restaurantId, array $filters = [])
    {
        $query = FinancialMovement::where('restaurant_id', $restaurantId)
            ->with(['financialAccount', 'creator']);

        if (! empty($filters['financial_account_id'])) {
            $query->where('financial_account_id', $filters['financial_account_id']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('movement_date', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('movement_date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')
                     ->paginate($filters['per_page'] ?? 20);
    }
}
