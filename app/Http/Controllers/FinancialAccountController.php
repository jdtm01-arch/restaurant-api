<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Services\FinancialAccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinancialAccountController extends Controller
{
    protected FinancialAccountService $service;

    public function __construct(FinancialAccountService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [FinancialAccount::class, $request->get('restaurant_id')]);

        $accounts = $this->service->list(
            $request->get('restaurant_id'),
            $request->only('type', 'is_active')
        );

        return response()->json(['data' => $accounts]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', [FinancialAccount::class, $request->get('restaurant_id')]);

        $validated = $request->validate([
            'name'      => 'required|string|max:120',
            'type'      => 'required|string|in:' . implode(',', FinancialAccount::TYPES),
            'currency'  => 'sometimes|string|size:3',
            'is_active' => 'sometimes|boolean',
        ]);

        $validated['restaurant_id'] = $request->get('restaurant_id');

        $account = $this->service->create($validated);

        return response()->json([
            'message' => 'Cuenta financiera creada exitosamente.',
            'data'    => $account,
        ], 201);
    }

    public function show(FinancialAccount $financialAccount): JsonResponse
    {
        $this->authorize('view', $financialAccount);

        $balance = FinancialAccountService::getAccountBalance(
            $financialAccount->id,
            $financialAccount->restaurant_id
        );

        return response()->json([
            'data' => array_merge($financialAccount->toArray(), ['balance' => $balance]),
        ]);
    }

    public function update(Request $request, FinancialAccount $financialAccount): JsonResponse
    {
        $this->authorize('update', $financialAccount);

        $validated = $request->validate([
            'name'      => 'sometimes|string|max:120',
            'type'      => 'sometimes|string|in:' . implode(',', FinancialAccount::TYPES),
            'currency'  => 'sometimes|string|size:3',
            'is_active' => 'sometimes|boolean',
        ]);

        try {
            $account = $this->service->update($financialAccount, $validated);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => ['is_active' => [$e->getMessage()]],
            ], 422);
        }

        return response()->json([
            'message' => 'Cuenta financiera actualizada.',
            'data'    => $account,
        ]);
    }

    public function destroy(FinancialAccount $financialAccount): JsonResponse
    {
        $this->authorize('delete', $financialAccount);

        try {
            $this->service->delete($financialAccount);
        } catch (\DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Cuenta financiera eliminada.']);
    }

    /**
     * Saldos de todas las cuentas + resumen consolidado.
     */
    public function balances(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [FinancialAccount::class, $request->get('restaurant_id')]);

        $data = FinancialAccountService::getAllBalances(
            $request->get('restaurant_id')
        );

        return response()->json(['data' => $data]);
    }
}
