<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [Product::class, $restaurantId]);

        $query = Product::with('productCategory')->orderBy('name');

        // Filtro por categoría
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filtro por estado activo
        if ($request->has('active')) {
            $query->where('is_active', (bool) $request->active);
        }

        // Búsqueda por texto
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Ordenamiento
        $allowedSorts = ['name', 'price_with_tax', 'created_at'];
        $sort = in_array($request->sort, $allowedSorts) ? $request->sort : 'name';
        $direction = in_array($request->direction, ['asc', 'desc']) ? $request->direction : 'asc';
        $query->reorder($sort, $direction);

        // Paginación
        $perPage = min((int) ($request->per_page ?? 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function store(StoreProductRequest $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('create', [Product::class, $restaurantId]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = Product::create([
            'restaurant_id' => $restaurantId,
            'category_id' => $request->category_id,
            'name' => $request->name,
            'description' => $request->description,
            'price_with_tax' => $request->price_with_tax,
            'image_path' => $imagePath,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Producto creado correctamente',
            'data' => $product
        ], 201);
    }

    public function show(Product $product)
    {
        $this->authorize('view', $product);

        $product->load('productCategory');
        return response()->json([
            'data' => $product
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $this->authorize('update', $product);

        $imagePath = $product->image_path;
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
            }
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product->update([
            'category_id' => $request->category_id,
            'name' => $request->name,
            'description' => $request->description,
            'price_with_tax' => $request->price_with_tax,
            'image_path' => $imagePath,
            'is_active' => $request->is_active ?? $product->is_active,
        ]);

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'data' => $product
        ]);
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        if ($product->image_path && Storage::disk('public')->exists($product->image_path)) {
            Storage::disk('public')->delete($product->image_path);
        }

        $product->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    public function restore(Product $product)
    {
        $this->authorize('restore', $product);

        $product->restore();

        return response()->json([
            'message' => 'Producto restaurado correctamente',
            'data' => $product->load('productCategory')
        ]);
    }

    public function toggleActive(Product $product)
    {
        $this->authorize('update', $product);

        $product->update(['is_active' => !$product->is_active]);

        return response()->json([
            'message' => 'Estado del producto actualizado correctamente',
            'data' => $product
        ]);
    }
}
