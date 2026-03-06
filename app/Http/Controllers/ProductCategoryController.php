<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductCategoryRequest;
use App\Http\Requests\UpdateProductCategoryRequest;
use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [ProductCategory::class, $restaurantId]);

        $categories = ProductCategory::orderBy('name')->get();

        return response()->json([
            'data' => $categories
        ]);
    }

    public function store(StoreProductCategoryRequest $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('create', [ProductCategory::class, $restaurantId]);

        $category = ProductCategory::create([
            'restaurant_id' => $restaurantId,
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Categoría creada correctamente',
            'data' => $category
        ], 201);
    }

    public function update(
        UpdateProductCategoryRequest $request,
        ProductCategory $product_category
    ) {
        $this->authorize('update', $product_category);

        $product_category->update([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Categoría actualizada correctamente',
            'data' => $product_category
        ]);
    }

    public function destroy(ProductCategory $product_category)
    {
        $this->authorize('delete', $product_category);

        if ($product_category->products()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar la categoría porque tiene productos asociados.',
            ], 422);
        }

        $product_category->delete();

        return response()->json([
            'message' => 'Categoría eliminada correctamente'
        ]);
    }
}