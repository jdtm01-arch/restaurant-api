<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Models\Table;
use Illuminate\Http\Request;

class TableController extends Controller
{
    public function index(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [Table::class, $restaurantId]);

        $tables = Table::orderBy('number')->get();

        return response()->json(['data' => $tables]);
    }

    public function store(StoreTableRequest $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('create', [Table::class, $restaurantId]);

        $table = Table::create([
            'restaurant_id' => $restaurantId,
            'number' => $request->number,
            'name' => $request->name,
            'is_active' => $request->is_active ?? true,
        ]);

        return response()->json([
            'message' => 'Mesa creada correctamente.',
            'data' => $table,
        ], 201);
    }

    public function show(Table $table)
    {
        $this->authorize('view', $table);

        return response()->json(['data' => $table]);
    }

    public function update(UpdateTableRequest $request, Table $table)
    {
        $this->authorize('update', $table);

        $table->update([
            'number' => $request->number,
            'name' => $request->name,
            'is_active' => $request->is_active ?? $table->is_active,
        ]);

        return response()->json([
            'message' => 'Mesa actualizada correctamente.',
            'data' => $table,
        ]);
    }

    public function destroy(Table $table)
    {
        $this->authorize('delete', $table);

        $table->delete();

        return response()->json(['message' => 'Mesa eliminada correctamente.']);
    }

    public function restore(Table $table)
    {
        $this->authorize('restore', $table);

        $table->restore();

        return response()->json([
            'message' => 'Mesa restaurada correctamente.',
            'data' => $table,
        ]);
    }

    /**
     * Batch-update table positions (x, y) for the floor map.
     */
    public function updatePositions(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('create', [Table::class, $restaurantId]);

        $request->validate([
            'positions'              => 'required|array|min:1',
            'positions.*.id'         => 'required|integer|exists:tables,id',
            'positions.*.position_x' => 'required|integer|min:0',
            'positions.*.position_y' => 'required|integer|min:0',
        ]);

        foreach ($request->positions as $pos) {
            Table::where('id', $pos['id'])
                ->where('restaurant_id', $restaurantId)
                ->update([
                    'position_x' => $pos['position_x'],
                    'position_y' => $pos['position_y'],
                ]);
        }

        return response()->json(['message' => 'Posiciones actualizadas correctamente.']);
    }
}
