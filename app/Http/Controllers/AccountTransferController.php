<?php

namespace App\Http\Controllers;

use App\Models\AccountTransfer;
use App\Services\AccountTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountTransferController extends Controller
{
    protected AccountTransferService $service;

    public function __construct(AccountTransferService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', [AccountTransfer::class, $request->get('restaurant_id')]);

        $transfers = $this->service->list(
            $request->get('restaurant_id'),
            $request->only('date_from', 'date_to', 'per_page')
        );

        return response()->json($transfers);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', [AccountTransfer::class, $request->get('restaurant_id')]);

        $validated = $request->validate([
            'from_account_id' => 'required|integer|exists:financial_accounts,id',
            'to_account_id'   => 'required|integer|exists:financial_accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'description'     => 'nullable|string|max:255',
        ]);

        $validated['restaurant_id'] = $request->get('restaurant_id');

        $transfer = $this->service->transfer($validated);

        return response()->json([
            'message' => 'Transferencia registrada exitosamente.',
            'data'    => $transfer,
        ], 201);
    }

    public function update(Request $request, \App\Models\AccountTransfer $accountTransfer): JsonResponse
    {
        $this->authorize('update', $accountTransfer);

        // Verify belongs to restaurant
        if ((int) $accountTransfer->restaurant_id !== (int) $request->get('restaurant_id')) {
            abort(403, 'No autorizado.');
        }

        $validated = $request->validate([
            'from_account_id' => 'required|integer|exists:financial_accounts,id',
            'to_account_id'   => 'required|integer|exists:financial_accounts,id',
            'amount'          => 'required|numeric|min:0.01',
            'description'     => 'nullable|string|max:255',
        ]);

        $transfer = $this->service->update($accountTransfer, $validated);

        return response()->json([
            'message' => 'Transferencia actualizada exitosamente.',
            'data'    => $transfer,
        ]);
    }

    public function destroy(Request $request, \App\Models\AccountTransfer $accountTransfer): JsonResponse
    {
        $this->authorize('delete', $accountTransfer);

        // Verify belongs to restaurant
        if ((int) $accountTransfer->restaurant_id !== (int) $request->get('restaurant_id')) {
            abort(403, 'No autorizado.');
        }

        $this->service->destroy($accountTransfer);

        return response()->json([
            'message' => 'Transferencia eliminada exitosamente.',
        ]);
    }
}
