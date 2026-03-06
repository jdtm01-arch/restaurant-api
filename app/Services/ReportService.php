<?php

namespace App\Services;

use App\Models\CashRegister;
use App\Models\Expense;
use App\Models\Order;
use App\Models\Sale;
use App\Models\WasteLog;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /*
    |--------------------------------------------------------------------------
    | 1. VENTAS POR CATEGORÍA Y CANAL
    |--------------------------------------------------------------------------
    */
    public function salesByCategory(int $restaurantId, string $dateFrom, string $dateTo, ?string $channel = null): array
    {
        $query = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('sales as s', 's.order_id', '=', 'o.id')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->join('product_categories as pc', 'p.category_id', '=', 'pc.id')
            ->where('s.restaurant_id', $restaurantId)
            ->whereDate('s.paid_at', '>=', $dateFrom)
            ->whereDate('s.paid_at', '<=', $dateTo);

        if ($channel) {
            $query->where('s.channel', $channel);
        }

        $results = $query->select([
                'pc.id as category_id',
                'pc.name as category_name',
                's.channel',
                DB::raw('SUM(oi.subtotal) as total'),
                DB::raw('SUM(oi.quantity) as quantity_sold'),
            ])
            ->groupBy('pc.id', 'pc.name', 's.channel')
            ->get();

        // Pivot by category
        $categories = [];
        $grandTotal = 0;

        foreach ($results as $row) {
            $catId = $row->category_id;
            if (! isset($categories[$catId])) {
                $categories[$catId] = [
                    'category_id'   => $catId,
                    'category_name' => $row->category_name,
                    'total'         => 0,
                    'quantity_sold' => 0,
                    'dine_in'       => 0,
                    'takeaway'      => 0,
                    'delivery'      => 0,
                ];
            }
            $categories[$catId]['total'] += $row->total;
            $categories[$catId]['quantity_sold'] += $row->quantity_sold;
            $categories[$catId][$row->channel] = (float) $row->total;
            $grandTotal += $row->total;
        }

        // Calculate percentages
        foreach ($categories as &$cat) {
            $cat['percentage_of_total'] = $grandTotal > 0
                ? round(($cat['total'] / $grandTotal) * 100, 2)
                : 0;
        }

        $totalDineIn = collect($categories)->sum('dine_in');
        $totalTakeaway = collect($categories)->sum('takeaway');
        $totalDelivery = collect($categories)->sum('delivery');

        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'totals' => [
                'total_sales'    => round($grandTotal, 2),
                'total_dine_in'  => round($totalDineIn, 2),
                'total_takeaway' => round($totalTakeaway, 2),
                'total_delivery' => round($totalDelivery, 2),
            ],
            'by_category' => array_values($categories),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 2. VENTAS POR HORARIO
    |--------------------------------------------------------------------------
    */
    public function salesByHour(int $restaurantId, string $dateFrom, string $dateTo): array
    {
        $driver = DB::getDriverName();
        $hourExpr = $driver === 'sqlite'
            ? "CAST(strftime('%H', paid_at) AS INTEGER)"
            : 'HOUR(paid_at)';

        $results = DB::table('sales')
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', '>=', $dateFrom)
            ->whereDate('paid_at', '<=', $dateTo)
            ->select([
                DB::raw("{$hourExpr} as hour"),
                DB::raw('SUM(total) as total_sales'),
                DB::raw('COUNT(*) as count_orders'),
            ])
            ->groupBy(DB::raw($hourExpr))
            ->orderBy('hour')
            ->get();

        $byHour = [];
        $totalSales = 0;
        $hoursWithSales = 0;
        $peakHour = null;
        $peakTotal = 0;

        for ($h = 0; $h < 24; $h++) {
            $found = $results->firstWhere('hour', $h);
            $hourData = [
                'hour'         => $h,
                'total_sales'  => $found ? round((float) $found->total_sales, 2) : 0,
                'count_orders' => $found ? (int) $found->count_orders : 0,
            ];
            $byHour[] = $hourData;

            if ($found) {
                $totalSales += $found->total_sales;
                $hoursWithSales++;
                if ($found->total_sales > $peakTotal) {
                    $peakTotal = $found->total_sales;
                    $peakHour = $h;
                }
            }
        }

        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'by_hour' => $byHour,
            'peak_hour' => $peakHour,
            'average_per_hour' => $hoursWithSales > 0 ? round($totalSales / $hoursWithSales, 2) : 0,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 3. ANULACIONES, CANCELACIONES Y DESCUENTOS
    |--------------------------------------------------------------------------
    */
    public function cancellationsAndDiscounts(int $restaurantId, string $dateFrom, string $dateTo): array
    {
        // Órdenes canceladas
        $cancelledOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', Order::STATUS_CANCELLED)
            ->whereDate('cancelled_at', '>=', $dateFrom)
            ->whereDate('cancelled_at', '<=', $dateTo)
            ->with('user')
            ->get();

        $cancelledDetails = $cancelledOrders->map(fn ($o) => [
            'order_id'     => $o->id,
            'total'        => (float) $o->total,
            'cancelled_at' => $o->cancelled_at?->format('Y-m-d H:i'),
            'cancelled_by' => $o->user?->name ?? 'N/A',
            'reason'       => $o->cancellation_reason,
        ]);

        // Descuentos aplicados
        $discountedOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('discount_percentage', '>', 0)
            ->whereHas('sale', function ($q) use ($dateFrom, $dateTo) {
                $q->whereDate('paid_at', '>=', $dateFrom)
                    ->whereDate('paid_at', '<=', $dateTo);
            })
            ->with('user')
            ->get();

        $discountDetails = $discountedOrders->map(fn ($o) => [
            'order_id'            => $o->id,
            'discount_percentage' => (float) $o->discount_percentage,
            'discount_amount'     => (float) $o->discount_amount,
            'applied_by'          => $o->user?->name ?? 'N/A',
        ]);

        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'cancelled_orders' => [
                'count'        => $cancelledOrders->count(),
                'total_amount' => round($cancelledOrders->sum('total'), 2),
                'details'      => $cancelledDetails->toArray(),
            ],
            'discounts_applied' => [
                'count'                      => $discountedOrders->count(),
                'total_discount_amount'      => round($discountedOrders->sum('discount_amount'), 2),
                'average_discount_percentage' => $discountedOrders->count() > 0
                    ? round($discountedOrders->avg('discount_percentage'), 2)
                    : 0,
                'details' => $discountDetails->toArray(),
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 4. VENTAS POR MESERO
    |--------------------------------------------------------------------------
    */
    public function salesByWaiter(int $restaurantId, string $dateFrom, string $dateTo): array
    {
        $results = DB::table('sales as s')
            ->join('orders as o', 's.order_id', '=', 'o.id')
            ->join('users as u', 'o.user_id', '=', 'u.id')
            ->where('s.restaurant_id', $restaurantId)
            ->whereDate('s.paid_at', '>=', $dateFrom)
            ->whereDate('s.paid_at', '<=', $dateTo)
            ->select([
                'u.id as user_id',
                'u.name as user_name',
                DB::raw('SUM(s.total) as total_sales'),
                DB::raw('COUNT(s.id) as count_orders'),
                DB::raw('AVG(s.total) as average_ticket'),
                DB::raw('SUM(s.discount_amount) as total_discounts_given'),
            ])
            ->groupBy('u.id', 'u.name')
            ->orderByDesc('total_sales')
            ->get();

        // Count cancelled orders per waiter
        $cancelled = DB::table('orders')
            ->where('restaurant_id', $restaurantId)
            ->where('status', Order::STATUS_CANCELLED)
            ->whereDate('cancelled_at', '>=', $dateFrom)
            ->whereDate('cancelled_at', '<=', $dateTo)
            ->select(['user_id', DB::raw('COUNT(*) as count')])
            ->groupBy('user_id')
            ->pluck('count', 'user_id');

        $byWaiter = $results->map(fn ($r) => [
            'user_id'              => $r->user_id,
            'user_name'            => $r->user_name,
            'total_sales'          => round((float) $r->total_sales, 2),
            'count_orders'         => (int) $r->count_orders,
            'count_cancelled'      => $cancelled[$r->user_id] ?? 0,
            'average_ticket'       => round((float) $r->average_ticket, 2),
            'total_discounts_given' => round((float) $r->total_discounts_given, 2),
        ]);

        return [
            'period'    => ['from' => $dateFrom, 'to' => $dateTo],
            'by_waiter' => $byWaiter->toArray(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 5. FOOD COST
    |--------------------------------------------------------------------------
    */
    public function foodCost(int $restaurantId, string $dateFrom, string $dateTo): array
    {
        $results = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('sales as s', 's.order_id', '=', 'o.id')
            ->join('products as p', 'oi.product_id', '=', 'p.id')
            ->join('product_categories as pc', 'p.category_id', '=', 'pc.id')
            ->where('s.restaurant_id', $restaurantId)
            ->whereDate('s.paid_at', '>=', $dateFrom)
            ->whereDate('s.paid_at', '<=', $dateTo)
            ->select([
                'p.id as product_id',
                'p.name as product_name',
                'pc.name as category_name',
                'pc.id as category_id',
                DB::raw('SUM(oi.price_with_tax_snapshot * oi.quantity) as revenue'),
                DB::raw('SUM(oi.product_cost_snapshot * oi.quantity) as cost'),
            ])
            ->groupBy('p.id', 'p.name', 'pc.id', 'pc.name')
            ->get();

        $totalRevenue = $results->sum('revenue');
        $totalCost = $results->sum('cost');
        $grossProfit = $totalRevenue - $totalCost;

        // By category
        $byCategory = $results->groupBy('category_id')->map(function ($items) {
            $first = $items->first();
            $revenue = $items->sum('revenue');
            $cost = $items->sum('cost');
            return [
                'category_name'       => $first->category_name,
                'revenue'             => round($revenue, 2),
                'cost'                => round($cost, 2),
                'profit'              => round($revenue - $cost, 2),
                'food_cost_percentage' => $revenue > 0 ? round(($cost / $revenue) * 100, 2) : 0,
            ];
        })->values()->toArray();

        // Top/worst margin products
        $productMargins = $results->map(function ($r) {
            $revenue = (float) $r->revenue;
            $cost = (float) $r->cost;
            return [
                'product_name' => $r->product_name,
                'revenue'      => round($revenue, 2),
                'cost'         => round($cost, 2),
                'margin'       => $revenue > 0 ? round((($revenue - $cost) / $revenue) * 100, 2) : 0,
            ];
        })->sortByDesc('margin');

        return [
            'period'  => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => [
                'total_revenue'         => round($totalRevenue, 2),
                'total_cost'            => round($totalCost, 2),
                'gross_profit'          => round($grossProfit, 2),
                'food_cost_percentage'  => $totalRevenue > 0 ? round(($totalCost / $totalRevenue) * 100, 2) : 0,
            ],
            'by_category'         => $byCategory,
            'top_margin_products' => $productMargins->take(5)->values()->toArray(),
            'worst_margin_products' => $productMargins->reverse()->take(5)->values()->toArray(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 6. MERMAS Y DESPERDICIOS
    |--------------------------------------------------------------------------
    */
    public function waste(int $restaurantId, string $dateFrom, string $dateTo): array
    {
        $logs = WasteLog::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('waste_date', '>=', $dateFrom)
            ->whereDate('waste_date', '<=', $dateTo)
            ->with('product')
            ->get();

        $byReason = $logs->groupBy('reason')->map(fn ($items, $reason) => [
            'reason' => $reason ?: 'sin_razón',
            'count'  => $items->count(),
            'cost'   => round($items->sum('estimated_cost'), 2),
        ])->values()->toArray();

        $byProduct = $logs->groupBy('product_id')->map(function ($items) {
            $first = $items->first();
            return [
                'product_name' => $first->product?->name ?? $first->description,
                'count'        => $items->count(),
                'cost'         => round($items->sum('estimated_cost'), 2),
            ];
        })->values()->toArray();

        return [
            'period'               => ['from' => $dateFrom, 'to' => $dateTo],
            'total_estimated_cost' => round($logs->sum('estimated_cost'), 2),
            'count_incidents'      => $logs->count(),
            'by_reason'            => $byReason,
            'by_product'           => $byProduct,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 7. CUENTAS POR PAGAR A PROVEEDORES
    |--------------------------------------------------------------------------
    */
    public function accountsPayable(int $restaurantId): array
    {
        $expenses = Expense::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereHas('status', fn ($q) => $q->whereIn('slug', ['pending', 'partial']))
            ->with(['supplier', 'payments', 'status'])
            ->get();

        $totalPending = 0;
        $totalPartial = 0;
        $countPending = 0;
        $countPartial = 0;

        $bySupplier = [];

        foreach ($expenses as $expense) {
            $paidAmount = $expense->payments->sum('amount');
            $remaining = $expense->amount - $paidAmount;
            $statusSlug = $expense->status->slug;

            if ($statusSlug === 'pending') {
                $totalPending += $remaining;
                $countPending++;
            } else {
                $totalPartial += $remaining;
                $countPartial++;
            }

            $supplierName = $expense->supplier?->name ?? 'Sin proveedor';
            $supplierId = $expense->supplier_id ?? 0;

            if (! isset($bySupplier[$supplierId])) {
                $bySupplier[$supplierId] = [
                    'supplier_id'         => $supplierId,
                    'supplier_name'       => $supplierName,
                    'total_pending'       => 0,
                    'count_expenses'      => 0,
                    'oldest_expense_date' => $expense->expense_date->format('Y-m-d'),
                    'expenses'            => [],
                ];
            }

            $bySupplier[$supplierId]['total_pending'] += $remaining;
            $bySupplier[$supplierId]['count_expenses']++;

            if ($expense->expense_date < $bySupplier[$supplierId]['oldest_expense_date']) {
                $bySupplier[$supplierId]['oldest_expense_date'] = $expense->expense_date->format('Y-m-d');
            }

            $bySupplier[$supplierId]['expenses'][] = [
                'id'           => $expense->id,
                'description'  => $expense->description,
                'amount'       => (float) $expense->amount,
                'paid_amount'  => round($paidAmount, 2),
                'remaining'    => round($remaining, 2),
                'status'       => $statusSlug,
                'expense_date' => $expense->expense_date->format('Y-m-d'),
            ];
        }

        return [
            'summary' => [
                'total_pending' => round($totalPending, 2),
                'total_partial' => round($totalPartial, 2),
                'count_pending' => $countPending,
                'count_partial' => $countPartial,
            ],
            'by_supplier' => array_values($bySupplier),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 8. FLUJO DE EFECTIVO DIARIO
    |--------------------------------------------------------------------------
    */
    public function dailyCashFlow(int $restaurantId, string $dateFrom, string $dateTo): array
    {
        $period = \Carbon\CarbonPeriod::create($dateFrom, $dateTo);
        $byDay = [];
        $totalCashSales   = 0;
        $totalOtherSales  = 0;
        $totalExpenses    = 0;
        $totalAllExpenses = 0;

        foreach ($period as $date) {
            $d = $date->format('Y-m-d');

            $cashRegister = CashRegister::withoutGlobalScopes()
                ->where('restaurant_id', $restaurantId)
                ->whereDate('date', $d)
                ->first();

            $openingAmount = $cashRegister ? (float) $cashRegister->opening_amount : 0;

            // Cash account: prefer from register, fall back to active cash account
            $cashAccountId = $cashRegister
                ? $cashRegister->financial_account_id
                : DB::table('financial_accounts')
                    ->where('restaurant_id', $restaurantId)
                    ->where('type', 'cash')
                    ->where('is_active', true)
                    ->value('id');

            $transfersIn  = 0.0;
            $transfersOut = 0.0;

            if ($cashAccountId) {
                $transfersIn = (float) DB::table('financial_movements')
                    ->where('financial_account_id', $cashAccountId)
                    ->where('type', 'transfer_in')
                    ->whereDate('movement_date', $d)
                    ->sum('amount');

                $transfersOut = (float) DB::table('financial_movements')
                    ->where('financial_account_id', $cashAccountId)
                    ->where('type', 'transfer_out')
                    ->whereDate('movement_date', $d)
                    ->sum('amount');
            }

            // Cash sales (payment method named 'Efectivo' or id=1 convention)
            $cashSales = DB::table('sale_payments as sp')
                ->join('sales as s', 'sp.sale_id', '=', 's.id')
                ->join('payment_methods as pm', 'sp.payment_method_id', '=', 'pm.id')
                ->where('s.restaurant_id', $restaurantId)
                ->whereDate('s.paid_at', $d)
                ->where('pm.name', 'like', '%efectivo%')
                ->sum('sp.amount');

            $otherSales = DB::table('sale_payments as sp')
                ->join('sales as s', 'sp.sale_id', '=', 's.id')
                ->join('payment_methods as pm', 'sp.payment_method_id', '=', 'pm.id')
                ->where('s.restaurant_id', $restaurantId)
                ->whereDate('s.paid_at', $d)
                ->where('pm.name', 'not like', '%efectivo%')
                ->sum('sp.amount');

            $dailySales = $cashSales + $otherSales;

            // Only sum expense payments made with cash payment method
            $cashExpenses = DB::table('expense_payments as ep')
                ->join('expenses as e', 'ep.expense_id', '=', 'e.id')
                ->join('payment_methods as pm', 'ep.payment_method_id', '=', 'pm.id')
                ->where('e.restaurant_id', $restaurantId)
                ->whereDate('ep.paid_at', $d)
                ->where('pm.name', 'like', '%efectivo%')
                ->sum('ep.amount');

            $allExpensesDay = DB::table('expense_payments as ep')
                ->join('expenses as e', 'ep.expense_id', '=', 'e.id')
                ->where('e.restaurant_id', $restaurantId)
                ->whereDate('ep.paid_at', $d)
                ->sum('ep.amount');

            $otherExpensesDay = $allExpensesDay - $cashExpenses;

            $expectedCash = $openingAmount + $cashSales - $cashExpenses + $transfersIn - $transfersOut;
            $actualCash   = $cashRegister ? (float) $cashRegister->closing_amount_real : null;
            $difference   = $actualCash !== null ? $actualCash - $expectedCash : null;

            $byDay[] = [
                'date'            => $d,
                'opening_amount'  => round($openingAmount, 2),
                'cash_sales'      => round((float) $cashSales, 2),
                'other_sales'     => round((float) $otherSales, 2),
                'total_sales'     => round((float) $dailySales, 2),
                'cash_expenses'   => round((float) $cashExpenses, 2),
                'other_expenses'  => round((float) $otherExpensesDay, 2),
                'total_expenses'  => round((float) $allExpensesDay, 2),
                'transfers_in'    => round($transfersIn, 2),
                'transfers_out'   => round($transfersOut, 2),
                'expected_cash'   => round($expectedCash, 2),
                'actual_cash'     => $actualCash !== null ? round($actualCash, 2) : null,
                'difference'      => $difference !== null ? round($difference, 2) : null,
                'net_flow'        => round($dailySales - $allExpensesDay, 2),
            ];

            $totalCashSales   += $cashSales;
            $totalOtherSales  += $otherSales;
            $totalExpenses    += $cashExpenses;
            $totalAllExpenses += $allExpensesDay;
        }

        $totalDays = count($byDay);

        return [
            'period' => ['from' => $dateFrom, 'to' => $dateTo],
            'by_day' => $byDay,
            'totals' => [
                'total_cash_sales'    => round($totalCashSales, 2),
                'total_other_sales'   => round($totalOtherSales, 2),
                'total_expenses'      => round($totalExpenses, 2),
                'total_all_expenses'  => round($totalAllExpenses, 2),
                'total_net_flow'      => round($totalCashSales + $totalOtherSales - $totalExpenses, 2),
                'average_daily_sales' => $totalDays > 0
                    ? round(($totalCashSales + $totalOtherSales) / $totalDays, 2)
                    : 0,
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 9. PRODUCTOS MÁS/MENOS VENDIDOS
    |--------------------------------------------------------------------------
    */
    public function topProducts(int $restaurantId, string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $results = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('sales as s', 's.order_id', '=', 'o.id')
            ->where('s.restaurant_id', $restaurantId)
            ->whereDate('s.paid_at', '>=', $dateFrom)
            ->whereDate('s.paid_at', '<=', $dateTo)
            ->select([
                'oi.product_name_snapshot as product_name',
                DB::raw('SUM(oi.quantity) as quantity_sold'),
                DB::raw('SUM(oi.subtotal) as revenue'),
            ])
            ->groupBy('oi.product_id', 'oi.product_name_snapshot')
            ->orderByDesc('quantity_sold')
            ->get();

        return [
            'period'        => ['from' => $dateFrom, 'to' => $dateTo],
            'top_sellers'   => $results->take($limit)->map(fn ($r) => [
                'product_name'  => $r->product_name,
                'quantity_sold' => (int) $r->quantity_sold,
                'revenue'       => round((float) $r->revenue, 2),
            ])->values()->toArray(),
            'least_sellers' => $results->reverse()->take($limit)->map(fn ($r) => [
                'product_name'  => $r->product_name,
                'quantity_sold' => (int) $r->quantity_sold,
                'revenue'       => round((float) $r->revenue, 2),
            ])->values()->toArray(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | 10. RESUMEN EJECUTIVO DEL DÍA
    |--------------------------------------------------------------------------
    */
    public function dailySummary(int $restaurantId, string $date): array
    {
        $totalSales = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $date)
            ->sum('total');

        $totalOrders = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $date)
            ->count();

        $avgTicket = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

        $totalExpenses = Expense::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('expense_date', $date)
            ->whereHas('status', fn ($q) => $q->where('slug', 'paid'))
            ->sum('amount');

        $netIncome = $totalSales - $totalExpenses;

        // Food cost
        $foodCostData = DB::table('order_items as oi')
            ->join('orders as o', 'oi.order_id', '=', 'o.id')
            ->join('sales as s', 's.order_id', '=', 'o.id')
            ->where('s.restaurant_id', $restaurantId)
            ->whereDate('s.paid_at', $date)
            ->select([
                DB::raw('SUM(oi.price_with_tax_snapshot * oi.quantity) as revenue'),
                DB::raw('SUM(oi.product_cost_snapshot * oi.quantity) as cost'),
            ])
            ->first();

        $foodCostPct = ($foodCostData && $foodCostData->revenue > 0)
            ? round(($foodCostData->cost / $foodCostData->revenue) * 100, 2)
            : 0;

        // Channel breakdown
        $channelData = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $date)
            ->selectRaw('channel, SUM(total) as total')
            ->groupBy('channel')
            ->pluck('total', 'channel');

        $dineInTotal = (float) ($channelData['dine_in'] ?? 0);
        $takeawayTotal = (float) ($channelData['takeaway'] ?? 0);
        $deliveryTotal = (float) ($channelData['delivery'] ?? 0);

        $dineInPct = $totalSales > 0 ? round(($dineInTotal / $totalSales) * 100, 1) : 0;
        $takeawayPct = $totalSales > 0 ? round(($takeawayTotal / $totalSales) * 100, 1) : 0;
        $deliveryPct = $totalSales > 0 ? round(($deliveryTotal / $totalSales) * 100, 1) : 0;

        // Cancelled orders
        $cancelledOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('status', Order::STATUS_CANCELLED)
            ->whereDate('cancelled_at', $date)
            ->count();

        // Waste
        $wasteTotal = WasteLog::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('waste_date', $date)
            ->sum('estimated_cost');

        // Cash register difference
        $register = CashRegister::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('date', $date)
            ->first();

        $registerDifference = $register ? (float) $register->difference : null;

        // Comparison vs yesterday
        $yesterday = \Carbon\Carbon::parse($date)->subDay()->format('Y-m-d');

        $yesterdaySales = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $yesterday)
            ->sum('total');

        $yesterdayOrders = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $yesterday)
            ->count();

        $salesChangePct = $yesterdaySales > 0
            ? round((($totalSales - $yesterdaySales) / $yesterdaySales) * 100, 1)
            : null;

        $ordersChangePct = $yesterdayOrders > 0
            ? round((($totalOrders - $yesterdayOrders) / $yesterdayOrders) * 100, 1)
            : null;

        return [
            'date'                       => $date,
            'total_sales'                => round((float) $totalSales, 2),
            'total_orders'               => $totalOrders,
            'average_ticket'             => round($avgTicket, 2),
            'total_expenses'             => round((float) $totalExpenses, 2),
            'net_income'                 => round((float) $netIncome, 2),
            'dine_in_pct'                => $dineInPct,
            'takeaway_pct'               => $takeawayPct,
            'delivery_pct'               => $deliveryPct,
            'cancelled_orders'           => $cancelledOrders,
            'cash_register_difference'   => $registerDifference,
        ];
    }
}
