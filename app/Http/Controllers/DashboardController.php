<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Order;
use App\Models\Sale;
use App\Models\Table;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /dashboard
     * Single endpoint for the admin dashboard widgets.
     * Accessible by admin_general and admin_restaurante.
     */
    public function index(Request $request): JsonResponse
    {
        $user         = $request->user();
        $restaurantId = $request->get('restaurant_id');

        $role = $user->roleForRestaurant($restaurantId);
        if (! $role || ! in_array($role->slug, ['admin_general', 'admin_restaurante'])) {
            abort(403, 'No tienes permiso para acceder al dashboard.');
        }

        $today     = Carbon::today()->toDateString();
        $yesterday = Carbon::yesterday()->toDateString();

        // ── 1. Total sales today (paid) ──────────────────────────
        $totalSalesToday = (float) Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $today)
            ->sum('total');

        // ── 2. Active orders (open + closed, not yet paid) ───────
        $activeOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', [Order::STATUS_OPEN, Order::STATUS_CLOSED])
            ->count();

        // ── 3. Table occupancy ───────────────────────────────────
        $totalTables    = Table::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->count();

        // Tables that have an active order right now
        $occupiedTables = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', [Order::STATUS_OPEN, Order::STATUS_CLOSED])
            ->whereNotNull('table_id')
            ->distinct('table_id')
            ->count('table_id');

        $occupancyPct = $totalTables > 0 ? round(($occupiedTables / $totalTables) * 100) : 0;

        // ── 4. Total expenses today (paid) ───────────────────────
        $totalExpensesToday = (float) Expense::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('expense_date', $today)
            ->whereHas('status', fn ($q) => $q->where('slug', 'paid'))
            ->sum('amount');

        // ── 5. Weekly sales (last 7 days including today) ────────
        $weekStart  = Carbon::today()->subDays(6)->toDateString();
        $period     = CarbonPeriod::create($weekStart, $today);
        $salesByDay = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereBetween(DB::raw('DATE(paid_at)'), [$weekStart, $today])
            ->select(DB::raw('DATE(paid_at) as sale_date'), DB::raw('SUM(total) as total'))
            ->groupBy('sale_date')
            ->pluck('total', 'sale_date');

        $dayLabels = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
        $weeklySales = [];
        foreach ($period as $date) {
            $d = $date->toDateString();
            $isToday = $d === $today;
            $weeklySales[] = [
                'date'  => $d,
                'label' => $isToday ? 'Hoy' : $dayLabels[$date->dayOfWeek],
                'total' => round((float) ($salesByDay[$d] ?? 0), 2),
            ];
        }

        // ── 6. Sales by category today ───────────────────────────
        $catRows = DB::table('order_items as oi')
            ->join('orders as o',         'oi.order_id',  '=', 'o.id')
            ->join('sales as s',           's.order_id',  '=', 'o.id')
            ->join('products as p',        'oi.product_id', '=', 'p.id')
            ->join('product_categories as pc', 'p.category_id', '=', 'pc.id')
            ->where('s.restaurant_id', $restaurantId)
            ->whereDate('s.paid_at', $today)
            ->select(
                'pc.id as category_id',
                'pc.name as category_name',
                DB::raw('SUM(oi.subtotal) as total'),
                DB::raw('SUM(oi.quantity) as quantity_sold'),
            )
            ->groupBy('pc.id', 'pc.name')
            ->orderByDesc('total')
            ->get();

        $grandCatTotal = $catRows->sum('total');
        $salesByCategory = $catRows->map(function ($row) use ($grandCatTotal) {
            return [
                'category_id'   => $row->category_id,
                'category_name' => $row->category_name,
                'total'         => round((float) $row->total, 2),
                'quantity_sold' => (int) $row->quantity_sold,
                'percentage'    => $grandCatTotal > 0
                    ? round(($row->total / $grandCatTotal) * 100, 1)
                    : 0,
            ];
        })->values()->toArray();

        return response()->json([
            'data' => [
                'today'               => $today,
                'total_sales_today'   => $totalSalesToday,
                'active_orders'       => $activeOrders,
                'total_tables'        => $totalTables,
                'occupied_tables'     => $occupiedTables,
                'occupancy_pct'       => $occupancyPct,
                'total_expenses_today' => $totalExpensesToday,
                'weekly_sales'        => $weeklySales,
                'sales_by_category'   => $salesByCategory,
            ],
        ]);
    }

    /**
     * GET /dashboard/waiter
     * Dashboard for mozo role: their sales, active orders, and order details for the day.
     */
    public function waiter(Request $request): JsonResponse
    {
        $user         = $request->user();
        $restaurantId = $request->get('restaurant_id');
        $today        = Carbon::today()->toDateString();

        // 1. Waiter's sales today (via orders they created that are paid)
        $waiterSales = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $today)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
            ->sum('total');

        $waiterSalesCount = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->whereDate('paid_at', $today)
            ->whereHas('order', fn ($q) => $q->where('user_id', $user->id))
            ->count();

        // 2. Active orders for this waiter (open + closed)
        $activeOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('user_id', $user->id)
            ->whereIn('status', [Order::STATUS_OPEN, Order::STATUS_CLOSED])
            ->with(['table', 'items'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($o) => [
                'id'         => $o->id,
                'status'     => $o->status,
                'channel'    => $o->channel,
                'table_name' => $o->table?->name ?? '—',
                'total'      => (float) $o->total,
                'items_count' => $o->items->count(),
                'opened_at'  => $o->opened_at,
            ]);

        // 3. All orders for today (including paid/cancelled)
        $todayOrders = Order::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId)
            ->where('user_id', $user->id)
            ->whereDate('created_at', $today)
            ->with(['table', 'items'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($o) => [
                'id'         => $o->id,
                'status'     => $o->status,
                'channel'    => $o->channel,
                'table_name' => $o->table?->name ?? '—',
                'total'      => (float) $o->total,
                'items_count' => $o->items->count(),
                'opened_at'  => $o->opened_at,
            ]);

        return response()->json([
            'data' => [
                'today'              => $today,
                'total_sales'        => round((float) $waiterSales, 2),
                'sales_count'        => $waiterSalesCount,
                'active_orders'      => $activeOrders,
                'active_orders_count' => $activeOrders->count(),
                'today_orders'       => $todayOrders,
                'today_orders_count' => $todayOrders->count(),
            ],
        ]);
    }
}
