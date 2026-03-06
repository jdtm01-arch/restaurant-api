<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWasteLogRequest;
use App\Http\Requests\UpdateWasteLogRequest;
use App\Models\WasteLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WasteLogController extends Controller
{
    public function index(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [WasteLog::class, $restaurantId]);

        $query = WasteLog::with(['product', 'user'])
            ->where('restaurant_id', $restaurantId);

        if ($request->filled('date_from')) {
            $query->where('waste_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('waste_date', '<=', $request->date_to);
        }

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        $logs = $query->orderByDesc('waste_date')
            ->paginate($request->input('per_page', 15));

        return response()->json($logs);
    }

    public function store(StoreWasteLogRequest $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('create', [WasteLog::class, $restaurantId]);

        $wasteLog = WasteLog::create(array_merge($request->validated(), [
            'restaurant_id' => $restaurantId,
            'user_id'       => Auth::id(),
        ]));

        return response()->json([
            'message' => 'Registro de merma creado correctamente.',
            'data'    => $wasteLog,
        ], 201);
    }

    public function show(WasteLog $wasteLog)
    {
        $this->authorize('view', $wasteLog);

        $wasteLog->load(['product', 'user']);

        return response()->json(['data' => $wasteLog]);
    }

    public function update(UpdateWasteLogRequest $request, WasteLog $wasteLog)
    {
        $this->authorize('update', $wasteLog);

        $wasteLog->update($request->validated());

        return response()->json([
            'message' => 'Registro de merma actualizado correctamente.',
            'data'    => $wasteLog,
        ]);
    }

    public function destroy(WasteLog $wasteLog)
    {
        $this->authorize('delete', $wasteLog);

        $wasteLog->delete();

        return response()->json(['message' => 'Registro de merma eliminado correctamente.']);
    }
}
