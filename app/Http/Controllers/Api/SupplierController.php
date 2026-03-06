<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $restaurantId = $request->get("restaurant_id");
        $this->authorize('viewAny', [Supplier::class, $restaurantId]);

        $suppliers = Supplier::orderBy('name')->get();

        return response()->json(['data' => $suppliers]);
    }

    public function store(StoreSupplierRequest $request)
    {
        $restaurantId = $request->get("restaurant_id");
        $this->authorize('create', [Supplier::class, $restaurantId]);

        $supplier = Supplier::create($request->validated());

        return response()->json([
            'message' => 'Proveedor creado correctamente.',
            'data' => $supplier,
        ], 201);
    }

    public function show(Supplier $supplier)
    {
        return response()->json(['data' => $supplier]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier)
    {
        $restaurantId = $request->get("restaurant_id");
        $this->authorize('update', [$supplier, $restaurantId]);

        $supplier->update($request->validated());

        return response()->json([
            'message' => 'Proveedor actualizado correctamente.',
            'data' => $supplier,
        ]);
    }

    public function destroy(Request $request, Supplier $supplier)
    {
        $restaurantId = $request->get("restaurant_id");
        $this->authorize('delete', [$supplier, $restaurantId]);

        if ($supplier->expenses()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el proveedor porque tiene gastos asociados.'
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Proveedor eliminado correctamente.'
        ]);
    }
}
