<?php

namespace App\Http\Controllers;

use App\Http\Requests\OpenCashRegisterRequest;
use App\Http\Requests\CloseCashRegisterRequest;
use App\Models\CashRegister;
use App\Services\CashRegisterService;
use Illuminate\Http\Request;

class CashRegisterController extends Controller
{
    protected CashRegisterService $service;

    public function __construct(CashRegisterService $service)
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
        $this->authorize('viewAny', [CashRegister::class, $restaurantId]);

        $registers = CashRegister::where('restaurant_id', $restaurantId)
            ->with(['opener', 'closer'])
            ->orderByDesc('date')
            ->paginate(15);

        return response()->json($registers);
    }

    /*
    |--------------------------------------------------------------------------
    | CURRENT
    |--------------------------------------------------------------------------
    */
    public function current(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [CashRegister::class, $restaurantId]);

        $register = $this->service->getOpenRegister($restaurantId);

        if (! $register) {
            return response()->json(['data' => null]);
        }

        $register->load(['opener']);

        return response()->json(['data' => $register]);
    }

    /*
    |--------------------------------------------------------------------------
    | OPEN
    |--------------------------------------------------------------------------
    */
    public function open(OpenCashRegisterRequest $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('open', [CashRegister::class, $restaurantId]);

        $register = $this->service->open([
            'restaurant_id'  => $restaurantId,
            'opening_amount' => $request->opening_amount,
            'notes'          => $request->notes,
        ]);

        return response()->json([
            'message' => 'Caja abierta correctamente.',
            'data' => $register,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE
    |--------------------------------------------------------------------------
    */
    public function close(CloseCashRegisterRequest $request, CashRegister $cashRegister)
    {
        $this->authorize('close', $cashRegister);

        $register = $this->service->close($cashRegister, $request->validated());

        return response()->json([
            'message' => 'Caja cerrada correctamente.',
            'data' => $register,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show(CashRegister $cashRegister)
    {
        $this->authorize('view', $cashRegister);

        $cashRegister->load(['opener', 'closer']);

        $data = $cashRegister->toArray();

        // If closed, include Z report data
        if ($cashRegister->isClosed()) {
            $data['z_report'] = $this->service->generateZReport($cashRegister);
        }

        return response()->json(['data' => $data]);
    }

    /*
    |--------------------------------------------------------------------------
    | X REPORT
    |--------------------------------------------------------------------------
    */
    public function xReport(CashRegister $cashRegister)
    {
        $this->authorize('xReport', $cashRegister);

        $report = $this->service->generateXReport($cashRegister);

        return response()->json(['data' => $report]);
    }
}
