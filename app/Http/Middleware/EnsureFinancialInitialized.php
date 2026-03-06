<?php

namespace App\Http\Middleware;

use App\Models\Restaurant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFinancialInitialized
{
    /**
     * Bloquea operaciones financieras si el restaurante no ha
     * completado la inicialización de cuentas financieras.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $restaurantId = $request->get('restaurant_id');

        if (! $restaurantId) {
            return response()->json(['message' => 'Restaurant context required'], 400);
        }

        $restaurant = Restaurant::find($restaurantId);

        if (! $restaurant || ! $restaurant->isFinancialInitialized()) {
            return response()->json([
                'error' => [
                    'message' => 'Las cuentas financieras del restaurante no han sido inicializadas. Contacte al administrador.',
                    'code'    => 'FINANCIAL_NOT_INITIALIZED',
                ],
            ], 403);
        }

        return $next($request);
    }
}
