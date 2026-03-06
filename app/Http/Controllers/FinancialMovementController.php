<?php

namespace App\Http\Controllers;

use App\Models\FinancialMovement;
use App\Services\FinancialMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialMovementController extends Controller
{
    protected FinancialMovementService $service;

    public function __construct(FinancialMovementService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [FinancialMovement::class, $request->get('restaurant_id')]);

        $movements = $this->service->list(
            $request->get('restaurant_id'),
            $request->only('financial_account_id', 'type', 'reference_type', 'date_from', 'date_to', 'per_page')
        );

        return response()->json($movements);
    }
}
