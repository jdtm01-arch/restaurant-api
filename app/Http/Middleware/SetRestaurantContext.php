<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRestaurantContext
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $restaurantId = $request->header('X-Restaurant-Id');

        if (!$restaurantId) {
            return response()->json(['message' => 'Restaurant context required'], 400);
        }

        $belongs = $user->restaurants()
                        ->where('restaurants.id', $restaurantId)
                        ->exists();

        if (!$belongs) {
            return response()->json(['message' => 'Unauthorized for this restaurant'], 403);
        }

        $request->attributes->set('restaurant_id', $restaurantId);

        return $next($request);
    }
}
