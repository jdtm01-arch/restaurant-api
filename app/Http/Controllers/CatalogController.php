<?php

namespace App\Http\Controllers;

use App\Models\ExpenseStatus;
use App\Models\PaymentMethod;
use App\Models\Role;
use Illuminate\Http\JsonResponse;

/**
 * Controlador de catálogos — datos de referencia de solo lectura.
 *
 * Requiere autenticación pero NO requiere X-Restaurant-Id
 * (los catálogos son globales al sistema).
 */
class CatalogController extends Controller
{
    /**
     * GET /catalogs/roles
     */
    public function roles(): JsonResponse
    {
        return response()->json([
            'data' => Role::select('id', 'name', 'slug')->get(),
        ]);
    }

    /**
     * GET /catalogs/payment-methods
     */
    public function paymentMethods(): JsonResponse
    {
        return response()->json([
            'data' => PaymentMethod::where('active', true)
                ->select('id', 'name')
                ->get(),
        ]);
    }

    /**
     * GET /catalogs/expense-statuses
     */
    public function expenseStatuses(): JsonResponse
    {
        return response()->json([
            'data' => ExpenseStatus::where('active', true)
                ->select('id', 'name', 'slug')
                ->get(),
        ]);
    }
}
