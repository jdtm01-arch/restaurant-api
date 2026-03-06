<?php

namespace App\Services;

use App\Models\FinancialAccount;
use Illuminate\Support\Facades\DB;

class FinancialAccountService
{
    /*
    |--------------------------------------------------------------------------
    | CRUD
    |--------------------------------------------------------------------------
    */

    public function list(int $restaurantId, array $filters = [])
    {
        $query = FinancialAccount::where('restaurant_id', $restaurantId);

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderBy('type')->orderBy('name')->get();
    }

    public function create(array $data): FinancialAccount
    {
        return FinancialAccount::create([
            'restaurant_id' => $data['restaurant_id'],
            'name'          => $data['name'],
            'type'          => $data['type'],
            'currency'      => $data['currency'] ?? 'PEN',
            'is_active'     => $data['is_active'] ?? true,
        ]);
    }

    public function update(FinancialAccount $account, array $data): FinancialAccount
    {
        // Block deactivation if the account still has a positive balance
        if (isset($data['is_active']) && $data['is_active'] === false && $account->is_active) {
            $balance = self::getAccountBalance($account->id, $account->restaurant_id);
            if ($balance > 0) {
                throw new \DomainException(
                    "No se puede desactivar la cuenta \"{$account->name}\" porque tiene un saldo de S/ "
                    . number_format($balance, 2)
                    . '. Transfiera o retire el saldo primero.'
                );
            }
        }

        $account->update([
            'name'      => $data['name'] ?? $account->name,
            'type'      => $data['type'] ?? $account->type,
            'currency'  => $data['currency'] ?? $account->currency,
            'is_active' => $data['is_active'] ?? $account->is_active,
        ]);

        return $account->fresh();
    }

    public function delete(FinancialAccount $account): void
    {
        // Bloquear si tiene movimientos
        if ($account->movements()->exists()) {
            throw new \DomainException(
                'No se puede eliminar esta cuenta porque tiene movimientos financieros asociados.'
            );
        }

        $account->delete();
    }

    /*
    |--------------------------------------------------------------------------
    | BALANCE — Cálculo dinámico de saldo
    |--------------------------------------------------------------------------
    */

    /**
     * Calcula el saldo de una cuenta sumando movimientos.
     * income + transfer_in - expense - transfer_out
     */
    public static function getAccountBalance(int $accountId, ?int $restaurantId = null): float
    {
        $query = DB::table('financial_movements')
            ->where('financial_account_id', $accountId);

        if ($restaurantId) {
            $query->where('restaurant_id', $restaurantId);
        }

        $result = $query->selectRaw("
            COALESCE(SUM(CASE WHEN type IN ('income', 'transfer_in', 'initial_balance') THEN amount ELSE 0 END), 0)
            - COALESCE(SUM(CASE WHEN type IN ('expense', 'transfer_out') THEN amount ELSE 0 END), 0)
            AS balance
        ")->first();

        return round((float) $result->balance, 2);
    }

    /**
     * Saldos agrupados de todas las cuentas de un restaurante.
     */
    public static function getAllBalances(int $restaurantId): array
    {
        $accounts = FinancialAccount::where('restaurant_id', $restaurantId)
            ->where('is_active', true)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $balances = DB::table('financial_movements')
            ->where('restaurant_id', $restaurantId)
            ->groupBy('financial_account_id')
            ->selectRaw("
                financial_account_id,
                COALESCE(SUM(CASE WHEN type IN ('income', 'transfer_in', 'initial_balance') THEN amount ELSE 0 END), 0)
                - COALESCE(SUM(CASE WHEN type IN ('expense', 'transfer_out') THEN amount ELSE 0 END), 0)
                AS balance
            ")
            ->pluck('balance', 'financial_account_id');

        $result = [
            'accounts' => [],
            'by_type'  => [
                'cash'    => 0,
                'bank'    => 0,
                'digital' => 0,
                'pos'     => 0,
            ],
            'total' => 0,
        ];

        foreach ($accounts as $account) {
            $balance = round((float) ($balances[$account->id] ?? 0), 2);

            $result['accounts'][] = [
                'id'       => $account->id,
                'name'     => $account->name,
                'type'     => $account->type,
                'currency' => $account->currency,
                'balance'  => $balance,
            ];

            $result['by_type'][$account->type] = round(
                ($result['by_type'][$account->type] ?? 0) + $balance, 2
            );
            $result['total'] = round($result['total'] + $balance, 2);
        }

        return $result;
    }
}
