<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseCategoryRequest;
use App\Http\Requests\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    /**
     * GET /expense-categories — Lista de categorías de gasto del restaurante.
     */
    public function index(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [ExpenseCategory::class, $restaurantId]);

        $categories = ExpenseCategory::where('restaurant_id', $restaurantId)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $categories]);
    }

    /**
     * GET /expense-categories/{expense_category} — Detalle de categoría.
     */
    public function show(ExpenseCategory $expense_category)
    {
        $this->authorize('view', $expense_category);

        return response()->json(['data' => $expense_category]);
    }

    /**
     * POST /expense-categories — Crear categoría de gasto.
     */
    public function store(StoreExpenseCategoryRequest $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('create', [ExpenseCategory::class, $restaurantId]);

        $category = ExpenseCategory::create([
            'restaurant_id' => $restaurantId,
            'name'          => $request->name,
        ]);

        return response()->json([
            'message' => 'Categoría de gasto creada correctamente',
            'data'    => $category,
        ], 201);
    }

    /**
     * PUT /expense-categories/{expense_category} — Actualizar categoría.
     */
    public function update(
        UpdateExpenseCategoryRequest $request,
        ExpenseCategory $expense_category
    ) {
        $this->authorize('update', $expense_category);

        $expense_category->update($request->only(['name', 'active']));

        return response()->json([
            'message' => 'Categoría de gasto actualizada correctamente',
            'data'    => $expense_category,
        ]);
    }

    /**
     * DELETE /expense-categories/{expense_category} — Eliminar categoría (soft delete).
     */
    public function destroy(ExpenseCategory $expense_category)
    {
        $this->authorize('delete', $expense_category);

        // Prevent deletion if has associated expenses
        if ($expense_category->expenses()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene gastos asociados.',
            ], 422);
        }

        $expense_category->delete();

        return response()->json([
            'message' => 'Categoría de gasto eliminada correctamente',
        ]);
    }
}
