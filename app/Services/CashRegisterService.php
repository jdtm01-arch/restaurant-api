<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Exceptions\CashRegister\CashRegisterAlreadyExistsException;
use App\Exceptions\CashRegister\CashRegisterAlreadyClosedException;
use App\Exceptions\CashRegister\NoCashRegisterOpenException;
use App\Exceptions\CashRegister\OpenOrdersExistException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class CashRegisterService
{
    protected AuditService $auditService;
    protected CashValidationService $cashValidation;

    public function __construct(AuditService $auditService, CashValidationService $cashValidation)
    {
        $this->auditService = $auditService;
        $this->cashValidation = $cashValidation;
    }

    /*
    |--------------------------------------------------------------------------
    | OPEN
    |--------------------------------------------------------------------------
    */
    public function open(array $data): CashRegister
    {
        $restaurantId = $data['restaurant_id'];
        $date = Carbon::today()->toDateString();

        // No debe existir caja para esta fecha
        $exists = CashRegister::where('restaurant_id', $restaurantId)
            ->whereDate('date', $date)
            ->exists();

        if ($exists) {
            throw new CashRegisterAlreadyExistsException();
        }

        // Verificar que no exista cierre contable para hoy
        if ($this->cashValidation->hasClosing($restaurantId, $date)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'cash_register' => ['No se puede abrir la caja: ya existe cierre contable para la fecha de hoy.'],
            ]);
        }

        // Auto-asignar cuenta financiera tipo cash
        $cashAccount = FinancialAccount::where('restaurant_id', $restaurantId)
            ->where('type', FinancialAccount::TYPE_CASH)
            ->where('is_active', true)
            ->first();

        // Validar que no exista cierre del dia anterior con monto mayor al de apertura
        $prevRegister = CashRegister::where('restaurant_id', $restaurantId)
            ->where('status', CashRegister::STATUS_CLOSED)
            ->whereDate('date', '<', $date)
            ->orderByDesc('date')
            ->first();

        if ($prevRegister && $prevRegister->closing_amount_real !== null) {
            $prevClosing = round((float) $prevRegister->closing_amount_real, 2);
            if (round((float) $data['opening_amount'], 2) < $prevClosing) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'opening_amount' => [
                        "El monto de apertura (S/ {$data['opening_amount']}) no puede ser menor al cierre del día anterior (S/ {$prevClosing})."
                    ],
                ]);
            }
        }

        // Validar monto de apertura contra saldo real de la cuenta
        if ($cashAccount) {
            $accountBalance = FinancialAccountService::getAccountBalance(
                $cashAccount->id,
                $restaurantId
            );

            if (round((float) $data['opening_amount'], 2) > round($accountBalance, 2)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'opening_amount' => [
                        "El monto de apertura (S/ {$data['opening_amount']}) excede el saldo disponible en la cuenta de efectivo (S/ {$accountBalance})."
                    ],
                ]);
            }
        }

        $register = CashRegister::create([
            'restaurant_id'      => $restaurantId,
            'financial_account_id' => $cashAccount?->id,
            'date'               => $date,
            'opened_by'          => Auth::id(),
            'opening_amount'     => $data['opening_amount'],
            'opened_at'          => now(),
            'status'             => CashRegister::STATUS_OPEN,
            'notes'              => $data['notes'] ?? null,
        ]);

        $this->auditService->log(
            $restaurantId, 'CashRegister', $register->id,
            AuditService::ACTION_OPENED,
            null,
            ['opening_amount' => $data['opening_amount']]
        );

        return $register;
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE
    |--------------------------------------------------------------------------
    */
    public function close(CashRegister $register, array $data): CashRegister
    {
        return DB::transaction(function () use ($register, $data) {

            if ($register->isClosed()) {
                throw new CashRegisterAlreadyClosedException();
            }

            // Verificar que no haya órdenes abiertas o por cobrar
            $pendingOrders = Order::where('restaurant_id', $register->restaurant_id)
                ->whereIn('status', [Order::STATUS_OPEN, Order::STATUS_CLOSED])
                ->exists();

            if ($pendingOrders) {
                throw new OpenOrdersExistException(
                    'Existen órdenes abiertas o por cobrar. Cierre, pague o cancele todas las órdenes antes de cerrar la caja.'
                );
            }

            // Calcular expected
            $cashSales     = $this->getCashSalesTotal($register);
            $cashExpenses  = $this->getCashExpensesTotal($register);
            $transfersIn   = $this->getCashTransfersIn($register);
            $transfersOut  = $this->getCashTransfersOut($register);
            $expected = $register->opening_amount + $cashSales - $cashExpenses + $transfersIn - $transfersOut;

            $realAmount = $data['closing_amount_real'];
            $difference = $realAmount - $expected;

            $register->update([
                'closed_by'                => Auth::id(),
                'closing_amount_expected'  => $expected,
                'closing_amount_real'      => $realAmount,
                'difference'               => $difference,
                'closed_at'                => now(),
                'status'                   => CashRegister::STATUS_CLOSED,
                'notes'                    => $data['notes'] ?? $register->notes,
            ]);

            $this->auditService->log(
                $register->restaurant_id, 'CashRegister', $register->id,
                AuditService::ACTION_CLOSED,
                ['status' => CashRegister::STATUS_OPEN],
                ['status' => CashRegister::STATUS_CLOSED, 'difference' => $difference]
            );

            return $register->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | GET OPEN REGISTER
    |--------------------------------------------------------------------------
    */
    public function getOpenRegister(int $restaurantId): ?CashRegister
    {
        return CashRegister::where('restaurant_id', $restaurantId)
            ->where('status', CashRegister::STATUS_OPEN)
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | REQUIRE OPEN REGISTER
    |--------------------------------------------------------------------------
    */
    public function requireOpenRegister(int $restaurantId): CashRegister
    {
        $register = $this->getOpenRegister($restaurantId);

        if (! $register) {
            throw new NoCashRegisterOpenException();
        }

        return $register;
    }

    /*
    |--------------------------------------------------------------------------
    | X REPORT (parcial, sin cerrar)
    |--------------------------------------------------------------------------
    */
    public function generateXReport(CashRegister $register): array
    {
        $cashSales    = $this->getCashSalesTotal($register);
        $otherSales   = $this->getOtherSalesTotal($register);
        $totalSales   = $cashSales + $otherSales;
        $cashExpenses = $this->getCashExpensesTotal($register);
        $transfersIn  = $this->getCashTransfersIn($register);
        $transfersOut = $this->getCashTransfersOut($register);

        $orderCounts = $this->getOrderCounts($register);

        // Desglose por método de pago
        $byPaymentMethod = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.cash_register_id', $register->id)
            ->groupBy('payment_methods.id', 'payment_methods.name')
            ->select(
                'payment_methods.name as payment_method',
                DB::raw('SUM(sale_payments.amount) as total'),
                DB::raw('COUNT(DISTINCT sale_payments.sale_id) as count')
            )
            ->get();

        return [
            'cash_register_id'          => $register->id,
            'date'                      => $register->date->toDateString(),
            'opening_amount'            => (float) $register->opening_amount,
            'total_sales_cash'          => $cashSales,
            'total_sales_other'         => $otherSales,
            'total_sales'               => $totalSales,
            'total_expenses_cash'       => $cashExpenses,
            'total_transfers_in'        => $transfersIn,
            'total_transfers_out'       => $transfersOut,
            'expected_cash_in_register' => (float) $register->opening_amount + $cashSales - $cashExpenses + $transfersIn - $transfersOut,
            'by_payment_method'         => $byPaymentMethod,
            'count_orders_total'        => $orderCounts['total'],
            'count_orders_closed'       => $orderCounts['closed'],
            'count_orders_cancelled'    => $orderCounts['cancelled'],
            'count_sales'               => $this->getSalesCount($register),
            'timestamp'                 => now()->toIso8601String(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Z REPORT (al cerrar)
    |--------------------------------------------------------------------------
    */
    public function generateZReport(CashRegister $register): array
    {
        $xData = $this->generateXReport($register);

        // Desglose por método de pago
        $byPaymentMethod = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.cash_register_id', $register->id)
            ->groupBy('payment_methods.id', 'payment_methods.name')
            ->select(
                'payment_methods.name as payment_method',
                DB::raw('SUM(sale_payments.amount) as total'),
                DB::raw('COUNT(DISTINCT sale_payments.sale_id) as count')
            )
            ->get();

        // Desglose por canal
        $byChannel = DB::table('sales')
            ->where('cash_register_id', $register->id)
            ->groupBy('channel')
            ->select(
                'channel',
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(*) as count')
            )
            ->get();

        // Promedio por ticket
        $salesCount = $xData['count_sales'];
        $averageTicket = $salesCount > 0 ? $xData['total_sales'] / $salesCount : 0;

        return array_merge($xData, [
            'closing_amount_real' => (float) $register->closing_amount_real,
            'difference'          => (float) $register->difference,
            'by_payment_method'   => $byPaymentMethod,
            'by_channel'          => $byChannel,
            'average_ticket'      => round($averageTicket, 2),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */
    protected function getCashSalesTotal(CashRegister $register): float
    {
        return (float) DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.cash_register_id', $register->id)
            ->where('payment_methods.name', 'Efectivo')
            ->sum('sale_payments.amount');
    }

    protected function getOtherSalesTotal(CashRegister $register): float
    {
        return (float) DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.cash_register_id', $register->id)
            ->where('payment_methods.name', '!=', 'Efectivo')
            ->sum('sale_payments.amount');
    }

    protected function getCashExpensesTotal(CashRegister $register): float
    {
        return (float) DB::table('expense_payments')
            ->join('expenses', 'expenses.id', '=', 'expense_payments.expense_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'expense_payments.payment_method_id')
            ->where('expenses.restaurant_id', $register->restaurant_id)
            ->whereDate('expense_payments.paid_at', $register->date)
            ->where('payment_methods.name', 'Efectivo')
            ->sum('expense_payments.amount');
    }

    protected function getOrderCounts(CashRegister $register): array
    {
        $date = $register->date->toDateString();

        $total = Order::where('restaurant_id', $register->restaurant_id)
            ->whereDate('opened_at', $date)
            ->count();

        $closed = Order::where('restaurant_id', $register->restaurant_id)
            ->whereDate('opened_at', $date)
            ->where('status', Order::STATUS_CLOSED)
            ->count();

        $cancelled = Order::where('restaurant_id', $register->restaurant_id)
            ->whereDate('opened_at', $date)
            ->where('status', Order::STATUS_CANCELLED)
            ->count();

        return compact('total', 'closed', 'cancelled');
    }

    protected function getSalesCount(CashRegister $register): int
    {
        return Sale::where('cash_register_id', $register->id)->count();
    }

    protected function getCashTransfersIn(CashRegister $register): float
    {
        if (! $register->financial_account_id) {
            return 0.0;
        }

        return (float) DB::table('financial_movements')
            ->where('financial_account_id', $register->financial_account_id)
            ->where('type', 'transfer_in')
            ->whereDate('movement_date', $register->date->toDateString())
            ->sum('amount');
    }

    protected function getCashTransfersOut(CashRegister $register): float
    {
        if (! $register->financial_account_id) {
            return 0.0;
        }

        return (float) DB::table('financial_movements')
            ->where('financial_account_id', $register->financial_account_id)
            ->where('type', 'transfer_out')
            ->whereDate('movement_date', $register->date->toDateString())
            ->sum('amount');
    }
}
