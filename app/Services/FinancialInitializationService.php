<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\FinancialMovement;
use App\Models\Restaurant;
use Illuminate\Support\Facades\DB;

class FinancialInitializationService
{
    /**
     * Inicializa las cuentas financieras de un restaurante con saldos iniciales.
     *
     * @param int   $restaurantId
     * @param array $accounts  [ ['id' => int, 'initial_balance' => float, 'description' => ?string], ... ]
     * @param int   $userId    ID del usuario que realiza la inicialización
     * @return array            Resumen de la inicialización
     */
    public function initialize(int $restaurantId, array $accounts, int $userId): array
    {
        return DB::transaction(function () use ($restaurantId, $accounts, $userId) {

            $restaurant = Restaurant::findOrFail($restaurantId);

            // No permitir doble inicialización
            if ($restaurant->isFinancialInitialized()) {
                throw new \DomainException(
                    'Las cuentas financieras de este restaurante ya fueron inicializadas.'
                );
            }

            // Verificar que no existan movimientos de tipo initial_balance
            $existingInitial = FinancialMovement::where('restaurant_id', $restaurantId)
                ->where('type', FinancialMovement::TYPE_INITIAL_BALANCE)
                ->exists();

            if ($existingInitial) {
                throw new \DomainException(
                    'Ya existen movimientos de saldo inicial para este restaurante.'
                );
            }

            $summary = [];
            $totalInitialized = 0;

            foreach ($accounts as $accountData) {
                $account = FinancialAccount::where('id', $accountData['id'])
                    ->where('restaurant_id', $restaurantId)
                    ->firstOrFail();

                $amount = round((float) ($accountData['initial_balance'] ?? 0), 2);

                // Solo crear movimiento si el monto es mayor a 0
                if ($amount > 0) {
                    FinancialMovement::create([
                        'restaurant_id'        => $restaurantId,
                        'financial_account_id' => $account->id,
                        'type'                 => FinancialMovement::TYPE_INITIAL_BALANCE,
                        'reference_type'       => FinancialMovement::REF_INITIAL_BALANCE,
                        'reference_id'         => $account->id,
                        'amount'               => $amount,
                        'description'          => $accountData['description']
                            ?? "Saldo inicial — {$account->name}",
                        'movement_date'        => now()->toDateString(),
                        'created_by'           => $userId,
                    ]);
                }

                $summary[] = [
                    'account_id'      => $account->id,
                    'account_name'    => $account->name,
                    'type'            => $account->type,
                    'initial_balance' => $amount,
                ];

                $totalInitialized += $amount;
            }

            // Marcar restaurante como inicializado
            $restaurant->update(['financial_initialized_at' => now()]);

            return [
                'restaurant_id'     => $restaurantId,
                'initialized_at'    => $restaurant->financial_initialized_at->toIso8601String(),
                'accounts'          => $summary,
                'total_initialized' => round($totalInitialized, 2),
            ];
        });
    }

    /**
     * Retorna el estado de inicialización financiera del restaurante.
     */
    public function getStatus(int $restaurantId): array
    {
        $restaurant = Restaurant::findOrFail($restaurantId);

        $accounts = FinancialAccount::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'currency']);

        return [
            'initialized'        => $restaurant->isFinancialInitialized(),
            'initialized_at'     => $restaurant->financial_initialized_at?->toIso8601String(),
            'accounts'           => $accounts,
            'has_accounts'       => $accounts->isNotEmpty(),
        ];
    }
}
