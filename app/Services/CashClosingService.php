<?php

namespace App\Services;

use App\Exceptions\CashClosing\CashRegisterNotClosedException;
use App\Exceptions\CashClosing\ClosingAlreadyExistsException;
use App\Exceptions\CashClosing\OpenOrdersExistForClosingException;
use App\Models\CashClosing;
use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashClosingService
{
    protected AuditService $auditService;

    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Ejecutar cierre contable para una fecha.
     */
    public function performClosing(int $restaurantId, string $date): CashClosing
    {
        return DB::transaction(function () use ($restaurantId, $date) {

            // 1. No debe existir cierre previo
            $exists = CashClosing::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->whereDate('date', $date)
                ->exists();

            if ($exists) {
                throw new ClosingAlreadyExistsException();
            }

            // 2. Caja del día debe estar cerrada
            $cashRegister = CashRegister::where('restaurant_id', $restaurantId)
                ->whereDate('date', $date)
                ->first();

            if ($cashRegister && $cashRegister->isOpen()) {
                throw new CashRegisterNotClosedException();
            }

            // 3. No deben existir órdenes abiertas
            $openOrders = Order::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->where('status', Order::STATUS_OPEN)
                ->exists();

            if ($openOrders) {
                throw new OpenOrdersExistForClosingException();
            }

            // 4. Calcular totales
            $summary = $this->calculateSummary($restaurantId, $date);

            // 5. Crear cierre
            $closing = CashClosing::create([
                'restaurant_id' => $restaurantId,
                'closed_by'     => Auth::id(),
                'date'          => $date,
                'total_sales'   => $summary['total_sales'],
                'total_expenses' => $summary['total_expenses'],
                'net_total'     => $summary['net_total'],
                'closed_at'     => now(),
            ]);

            $this->auditService->log(
                $restaurantId, 'CashClosing', $closing->id,
                AuditService::ACTION_CLOSED,
                null,
                ['total_sales' => $summary['total_sales'], 'total_expenses' => $summary['total_expenses'], 'net_total' => $summary['net_total']]
            );

            return $closing;
        });
    }

    /**
     * Previsualizar totales para una fecha (sin persistir).
     */
    public function calculateSummary(int $restaurantId, string $date): array
    {
        $salesQuery = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $date);

        $totalSales = (clone $salesQuery)->sum('total');
        $salesCount = (clone $salesQuery)->count();

        $expensesQuery = Expense::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('expense_date', $date)
            ->whereHas('status', fn ($q) => $q->where('slug', 'paid'));

        $totalExpenses = (clone $expensesQuery)->sum('amount');
        $expensesCount = (clone $expensesQuery)->count();

        // Desglose por canal
        $salesByChannel = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $date)
            ->selectRaw('channel, SUM(total) as total, COUNT(*) as count')
            ->groupBy('channel')
            ->get()
            ->keyBy('channel')
            ->toArray();

        // Desglose por método de pago
        $salesByPaymentMethod = SalePayment::whereHas('sale', function ($q) use ($restaurantId, $date) {
                $q->withoutGlobalScopes()
                    ->where('restaurant_id', $restaurantId)
                    ->whereDate('paid_at', $date);
            })
            ->join('payment_methods', 'sale_payments.payment_method_id', '=', 'payment_methods.id')
            ->selectRaw('payment_methods.name as method, SUM(sale_payments.amount) as total')
            ->groupBy('payment_methods.id', 'payment_methods.name')
            ->get()
            ->toArray();

        return [
            'date'               => $date,
            'total_sales'        => round((float) $totalSales, 2),
            'total_expenses'     => round((float) $totalExpenses, 2),
            'net_total'          => round((float) $totalSales - (float) $totalExpenses, 2),
            'sales_count'        => $salesCount,
            'expenses_count'     => $expensesCount,
            'sales_by_channel'   => $salesByChannel,
            'sales_by_payment'   => $salesByPaymentMethod,
        ];
    }
}
