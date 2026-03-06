<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    protected ReceiptService $receiptService;

    public function __construct(ReceiptService $receiptService)
    {
        $this->receiptService = $receiptService;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [Sale::class, $restaurantId]);

        $query = Sale::with(['payments.paymentMethod', 'user', 'order.table'])
            ->where('restaurant_id', $restaurantId);

        // Filtros
        if ($request->filled('date_from')) {
            $query->where('paid_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('paid_at', '<=', $request->date_to . ' 23:59:59');
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('payment_method_id')) {
            $query->whereHas('payments', function ($q) use ($request) {
                $q->where('payment_method_id', $request->payment_method_id);
            });
        }

        $sales = $query->orderByDesc('paid_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($sales);
    }

    /*
    |--------------------------------------------------------------------------
    | SUMMARY — totals by payment method
    |--------------------------------------------------------------------------
    */
    public function summary(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [Sale::class, $restaurantId]);

        $query = Sale::withoutGlobalScopes()
            ->where('restaurant_id', $restaurantId);

        if ($request->filled('date_from')) {
            $query->where('paid_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('paid_at', '<=', $request->date_to . ' 23:59:59');
        }

        $totalGeneral = (float) (clone $query)->sum('total');

        // Get totals grouped by payment method
        $byMethod = DB::table('sale_payments as sp')
            ->join('sales as s', 'sp.sale_id', '=', 's.id')
            ->join('payment_methods as pm', 'sp.payment_method_id', '=', 'pm.id')
            ->where('s.restaurant_id', $restaurantId)
            ->when($request->filled('date_from'), fn ($q) => $q->where('s.paid_at', '>=', $request->date_from))
            ->when($request->filled('date_to'), fn ($q) => $q->where('s.paid_at', '<=', $request->date_to . ' 23:59:59'))
            ->select('pm.name as method_name', DB::raw('SUM(sp.amount) as total'))
            ->groupBy('pm.name')
            ->orderByDesc('total')
            ->get();

        return response()->json([
            'data' => [
                'total_general' => round($totalGeneral, 2),
                'by_method'     => $byMethod->map(fn ($r) => [
                    'method_name' => $r->method_name,
                    'total'       => round((float) $r->total, 2),
                ])->toArray(),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show(Sale $sale)
    {
        $this->authorize('view', $sale);

        $sale->load(['payments.paymentMethod', 'user', 'order.items', 'order.table']);

        return response()->json(['data' => $sale]);
    }

    /*
    |--------------------------------------------------------------------------
    | RECEIPT
    |--------------------------------------------------------------------------
    */
    public function receipt(Sale $sale)
    {
        $this->authorize('receipt', $sale);

        $receiptData = $this->receiptService->generateReceiptData($sale);
        $printFormat = $this->receiptService->formatForPrint($sale);

        return response()->json([
            'data' => [
                'receipt' => $receiptData,
                'text'    => $printFormat,
            ],
        ]);
    }
}
