<?php

namespace App\Services;

use App\Models\AccountTransfer;
use App\Models\CashClosing;
use App\Models\CashRegister;
use App\Models\FinancialAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountTransferService
{
    protected FinancialMovementService $movementService;
    protected CashValidationService $cashValidation;

    public function __construct(
        FinancialMovementService $movementService,
        CashValidationService $cashValidation
    ) {
        $this->movementService = $movementService;
        $this->cashValidation = $cashValidation;
    }

    /**
     * Realizar una transferencia entre cuentas financieras.
     * Genera 2 financial_movements (transfer_out + transfer_in) dentro de una transacción.
     */
    public function transfer(array $data): AccountTransfer
    {
        return DB::transaction(function () use ($data) {

            $restaurantId  = $data['restaurant_id'];
            $fromAccountId = $data['from_account_id'];
            $toAccountId   = $data['to_account_id'];
            $amount        = (float) $data['amount'];

            // 1. No permitir misma cuenta
            if ($fromAccountId === $toAccountId) {
                throw ValidationException::withMessages([
                    'to_account_id' => ['La cuenta destino no puede ser la misma que la cuenta origen.'],
                ]);
            }

            // 2. Validar que ambas cuentas existan y pertenezcan al restaurante
            $fromAccount = FinancialAccount::where('restaurant_id', $restaurantId)
                ->where('id', $fromAccountId)
                ->firstOrFail();

            $toAccount = FinancialAccount::where('restaurant_id', $restaurantId)
                ->where('id', $toAccountId)
                ->firstOrFail();

            // 3. Verificar que la caja no esté cerrada para la cuenta de efectivo
            $this->assertCashNotFrozen([$fromAccount, $toAccount], $restaurantId, Carbon::today()->toDateString());

            // 4. Validar saldo suficiente
            $balance = FinancialAccountService::getAccountBalance($fromAccountId, $restaurantId);

            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => ["Saldo insuficiente. Disponible: S/ " . number_format($balance, 2)],
                ]);
            }

            // 5. Crear transferencia
            $transfer = AccountTransfer::create([
                'restaurant_id'  => $restaurantId,
                'from_account_id' => $fromAccountId,
                'to_account_id'   => $toAccountId,
                'amount'          => $amount,
                'description'     => $data['description'] ?? null,
                'created_by'      => Auth::id(),
            ]);

            // 5. Crear par de movimientos financieros
            $this->movementService->createForTransfer($transfer, $restaurantId);

            return $transfer->load(['fromAccount', 'toAccount', 'creator']);
        });
    }

    /**
     * Listar transferencias de un restaurante.
     */
    public function list(int $restaurantId, array $filters = [])
    {
        $query = AccountTransfer::where('restaurant_id', $restaurantId)
            ->with(['fromAccount', 'toAccount', 'creator']);

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at')
                     ->paginate($filters['per_page'] ?? 20);
    }

    /**
     * Actualizar una transferencia existente (máximo 5 días desde su creación).
     */
    public function update(AccountTransfer $transfer, array $data): AccountTransfer
    {
        return DB::transaction(function () use ($transfer, $data) {

            // 1. Validar que no supere 5 días
            $daysSinceCreation = $transfer->created_at->diffInDays(now());
            if ($daysSinceCreation > 5) {
                throw ValidationException::withMessages([
                    'transfer' => ['No se puede editar una transferencia con más de 5 días de antigüedad.'],
                ]);
            }

            $restaurantId  = $transfer->restaurant_id;
            $fromAccountId = $data['from_account_id'];
            $toAccountId   = $data['to_account_id'];
            $amount        = (float) $data['amount'];

            // 2. No permitir misma cuenta
            if ($fromAccountId === $toAccountId) {
                throw ValidationException::withMessages([
                    'to_account_id' => ['La cuenta destino no puede ser la misma que la cuenta origen.'],
                ]);
            }

            // 3. Validar que ambas cuentas existan y pertenezcan al restaurante
            $fromAccount = FinancialAccount::where('restaurant_id', $restaurantId)
                ->where('id', $fromAccountId)
                ->firstOrFail();

            $toAccount = FinancialAccount::where('restaurant_id', $restaurantId)
                ->where('id', $toAccountId)
                ->firstOrFail();

            // 3b. Verificar que la caja no esté cerrada para la cuenta de efectivo
            $this->assertCashNotFrozen([$fromAccount, $toAccount], $restaurantId, $transfer->created_at->toDateString());

            // 4. Eliminar movimientos financieros antiguos
            \App\Models\FinancialMovement::where('reference_type', \App\Models\FinancialMovement::REF_TRANSFER)
                ->where('reference_id', $transfer->id)
                ->delete();

            // 5. Validar saldo suficiente (sin los movimientos antiguos)
            $balance = FinancialAccountService::getAccountBalance($fromAccountId, $restaurantId);
            if ($amount > $balance) {
                throw ValidationException::withMessages([
                    'amount' => ["Saldo insuficiente. Disponible: S/ " . number_format($balance, 2)],
                ]);
            }

            // 6. Actualizar transferencia
            $transfer->update([
                'from_account_id' => $fromAccountId,
                'to_account_id'   => $toAccountId,
                'amount'          => $amount,
                'description'     => $data['description'] ?? null,
            ]);

            // 7. Crear nuevos movimientos financieros
            $this->movementService->createForTransfer($transfer, $restaurantId);

            return $transfer->load(['fromAccount', 'toAccount', 'creator']);
        });
    }

    /**
     * Eliminar una transferencia (solo super-admin).
     */
    public function destroy(AccountTransfer $transfer): void
    {
        DB::transaction(function () use ($transfer) {
            // Verificar que la caja no esté cerrada para la cuenta de efectivo
            $transfer->load(['fromAccount', 'toAccount']);
            $this->assertCashNotFrozen(
                [$transfer->fromAccount, $transfer->toAccount],
                $transfer->restaurant_id,
                $transfer->created_at->toDateString()
            );

            // 1. Eliminar movimientos financieros asociados
            \App\Models\FinancialMovement::where('reference_type', \App\Models\FinancialMovement::REF_TRANSFER)
                ->where('reference_id', $transfer->id)
                ->delete();

            // 2. Eliminar transferencia
            $transfer->delete();
        });
    }

    /**
     * Verifica que ninguna cuenta involucrada sea de tipo efectivo
     * con la caja registradora ya cerrada en la fecha dada.
     * También verifica que no exista cierre contable para esa fecha.
     */
    private function assertCashNotFrozen(array $accounts, int $restaurantId, string $date): void
    {
        // 1. Verificar cierre contable (bloquea TODAS las cuentas, no solo efectivo)
        if ($this->cashValidation->hasClosing($restaurantId, $date)) {
            throw ValidationException::withMessages([
                'transfer' => [
                    "No se puede realizar esta operación: ya existe cierre contable para la fecha {$date}. "
                    . "Las transferencias quedan bloqueadas tras el cierre contable."
                ],
            ]);
        }

        // 2. Verificar caja cerrada para cuentas de efectivo
        foreach ($accounts as $account) {
            if ($account && $account->type === FinancialAccount::TYPE_CASH) {
                $closed = CashRegister::where('restaurant_id', $restaurantId)
                    ->whereDate('date', $date)
                    ->where('status', CashRegister::STATUS_CLOSED)
                    ->exists();

                if ($closed) {
                    throw ValidationException::withMessages([
                        'transfer' => [
                            "No se puede realizar esta operación: la caja registradora del {$date} ya está cerrada. "
                            . "Las transferencias que involucran la cuenta de efectivo quedan bloqueadas tras el cierre."
                        ],
                    ]);
                }
                break;
            }
        }
    }
}
