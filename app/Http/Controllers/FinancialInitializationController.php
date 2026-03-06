<?php

namespace App\Http\Controllers;

use App\Services\FinancialInitializationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialInitializationController extends Controller
{
    protected FinancialInitializationService $service;

    public function __construct(FinancialInitializationService $service)
    {
        $this->service = $service;
    }

    /**
     * GET /financial/status
     * Retorna el estado de inicialización del restaurante actual.
     */
    public function status(Request $request): JsonResponse
    {
        $data = $this->service->getStatus($request->get('restaurant_id'));

        return response()->json(['data' => $data]);
    }

    /**
     * POST /financial/initialize
     * Solo admin_general puede inicializar las cuentas financieras.
     */
    public function initialize(Request $request): JsonResponse
    {
        // Solo admin_general
        $user = $request->user();
        if (! $user->hasRoleInRestaurant('admin_general', $request->get('restaurant_id'))) {
            return response()->json([
                'message' => 'Solo el administrador general puede inicializar las cuentas financieras.',
            ], 403);
        }

        $validated = $request->validate([
            'accounts'                    => ['required', 'array', 'min:1'],
            'accounts.*.id'               => ['required', 'integer', 'exists:financial_accounts,id'],
            'accounts.*.initial_balance'  => ['required', 'numeric', 'min:0'],
            'accounts.*.description'      => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $this->service->initialize(
                $request->get('restaurant_id'),
                $validated['accounts'],
                $user->id
            );

            return response()->json([
                'message' => 'Cuentas financieras inicializadas exitosamente.',
                'data'    => $result,
            ], 201);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
