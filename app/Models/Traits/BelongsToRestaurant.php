<?php

namespace App\Models\Traits;

trait BelongsToRestaurant
{
    public function resolveRouteBinding($value, $field = null)
    {
        $restaurantId = request()->header('X-Restaurant-Id');

        if (! $restaurantId) {
            abort(400, 'Restaurant context required');
        }

        $query = $this->newQuery();

        // Permitir withTrashed solo en restore
        if (request()->routeIs($this->getRestoreRouteName())) {
            $query = $query->withTrashed();
        }

        return $query
            ->where($field ?? $this->getRouteKeyName(), $value)
            ->where('restaurant_id', $restaurantId)
            ->firstOrFail();
    }

    /**
     * Cada modelo puede sobrescribir esto si su ruta de restore cambia.
     */
    protected function getRestoreRouteName(): string
    {
        return 'products.restore';
    }
}