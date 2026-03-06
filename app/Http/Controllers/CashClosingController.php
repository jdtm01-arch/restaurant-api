<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerformCashClosingRequest;
use App\Models\CashClosing;
use App\Services\CashClosingService;
use Illuminate\Http\Request;

class CashClosingController extends Controller
{
    protected CashClosingService $service;

    public function __construct(CashClosingService $service)
    {
        $this->service = $service;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [CashClosing::class, $restaurantId]);

        $closings = CashClosing::where('restaurant_id', $restaurantId)
            ->with('closedBy')
            ->orderByDesc('date')
            ->paginate(15);

        return response()->json($closings);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show(CashClosing $cashClosing)
    {
        $this->authorize('view', $cashClosing);

        $cashClosing->load('closedBy');

        // Incluir desglose
        $summary = $this->service->calculateSummary(
            $cashClosing->restaurant_id,
            $cashClosing->date->format('Y-m-d')
        );

        return response()->json([
            'data' => array_merge($cashClosing->toArray(), [
                'breakdown' => $summary,
            ]),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PREVIEW
    |--------------------------------------------------------------------------
    */
    public function preview(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('create', [CashClosing::class, $restaurantId]);

        $request->validate([
            'date' => ['required', 'date'],
        ]);

        $summary = $this->service->calculateSummary($restaurantId, $request->date);

        return response()->json(['data' => $summary]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(PerformCashClosingRequest $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('create', [CashClosing::class, $restaurantId]);

        $closing = $this->service->performClosing($restaurantId, $request->date);

        $closing->load('closedBy');

        return response()->json([
            'message' => 'Cierre contable realizado correctamente.',
            'data'    => $closing,
        ], 201);
    }
}
