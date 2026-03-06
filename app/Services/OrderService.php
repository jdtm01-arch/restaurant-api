<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Table;
use App\Exceptions\Order\OrderNotOpenException;
use App\Exceptions\Order\OrderNotClosedException;
use App\Exceptions\Order\TableHasActiveOrderException;
use App\Exceptions\Order\EmptyOrderException;
use App\Exceptions\Order\ProductNotAvailableException;
use App\Exceptions\Order\InvalidChannelTableException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderService
{
    protected CashRegisterService $cashRegisterService;
    protected AuditService $auditService;

    public function __construct(CashRegisterService $cashRegisterService, AuditService $auditService)
    {
        $this->cashRegisterService = $cashRegisterService;
        $this->auditService = $auditService;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create(array $data): Order
    {
        return DB::transaction(function () use ($data) {

            $restaurantId = $data['restaurant_id'];

            // 1. Verificar caja abierta
            $this->cashRegisterService->requireOpenRegister($restaurantId);

            // 2. Validar canal y mesa
            $channel = $data['channel'];
            $tableId = $data['table_id'] ?? null;

            if ($channel === Order::CHANNEL_DINE_IN) {
                if (! $tableId) {
                    throw new InvalidChannelTableException('Canal dine_in requiere una mesa.');
                }

                $table = Table::where('restaurant_id', $restaurantId)
                    ->where('id', $tableId)
                    ->where('is_active', true)
                    ->first();

                if (! $table) {
                    throw new InvalidChannelTableException('Mesa no encontrada, no pertenece al restaurante o no está activa.');
                }

                // Verificar que la mesa no tenga orden abierta
                $hasOpen = Order::where('table_id', $tableId)
                    ->where('status', Order::STATUS_OPEN)
                    ->exists();

                if ($hasOpen) {
                    throw new TableHasActiveOrderException();
                }

            } elseif ($channel === Order::CHANNEL_TAKEAWAY || $channel === Order::CHANNEL_DELIVERY) {
                if ($tableId) {
                    throw new InvalidChannelTableException("Canal {$channel} no permite mesa.");
                }
            }

            // 3. Crear orden
            $order = Order::create([
                'restaurant_id' => $restaurantId,
                'table_id'      => $tableId,
                'user_id'       => Auth::id(),
                'channel'       => $channel,
                'status'        => Order::STATUS_OPEN,
                'opened_at'     => now(),
            ]);

            $this->auditService->log(
                $restaurantId, 'Order', $order->id,
                AuditService::ACTION_CREATED,
                null,
                ['channel' => $channel, 'table_id' => $tableId]
            );

            return $order;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | ADD ITEM
    |--------------------------------------------------------------------------
    */
    public function addItem(Order $order, array $data): OrderItem
    {
        return DB::transaction(function () use ($order, $data) {

            if (! $order->isOpen()) {
                throw new OrderNotOpenException();
            }

            // Verificar caja abierta
            $this->cashRegisterService->requireOpenRegister($order->restaurant_id);

            $product = Product::where('id', $data['product_id'])
                ->where('restaurant_id', $order->restaurant_id)
                ->where('is_active', true)
                ->first();

            if (! $product) {
                throw new ProductNotAvailableException();
            }

            $notes = $data['notes'] ?? null;

            // Si el mismo producto ya existe sin notas o con las mismas notas, sumar cantidad
            $existingItem = $order->items()
                ->where('product_id', $product->id)
                ->where('notes', $notes)
                ->first();

            if ($existingItem) {
                $existingItem->quantity += $data['quantity'];
                $existingItem->subtotal = $existingItem->price_with_tax_snapshot * $existingItem->quantity;
                $existingItem->save();

                $this->recalculateTotals($order);

                return $existingItem;
            }

            $item = OrderItem::create([
                'order_id'                => $order->id,
                'product_id'              => $product->id,
                'product_name_snapshot'   => $product->name,
                'product_cost_snapshot'   => $product->cost ?? 0,
                'price_with_tax_snapshot' => $product->price_with_tax,
                'quantity'                => $data['quantity'],
                'subtotal'                => $product->price_with_tax * $data['quantity'],
                'notes'                   => $notes,
            ]);

            $this->recalculateTotals($order);

            return $item;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE ITEM
    |--------------------------------------------------------------------------
    */
    public function removeItem(Order $order, OrderItem $item): void
    {
        if (! $order->isOpen() && ! $order->isClosed()) {
            throw new OrderNotOpenException();
        }

        // Verificar caja abierta
        $this->cashRegisterService->requireOpenRegister($order->restaurant_id);

        $item->delete();

        $this->recalculateTotals($order);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ITEM QUANTITY
    |--------------------------------------------------------------------------
    */
    public function updateItemQuantity(Order $order, OrderItem $item, int $quantity): OrderItem
    {
        if (! $order->isOpen()) {
            throw new OrderNotOpenException();
        }

        // Verificar caja abierta
        $this->cashRegisterService->requireOpenRegister($order->restaurant_id);

        $item->update([
            'quantity' => $quantity,
            'subtotal' => $item->price_with_tax_snapshot * $quantity,
        ]);

        $this->recalculateTotals($order);

        return $item->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | APPLY DISCOUNT
    |--------------------------------------------------------------------------
    */
    public function applyDiscount(Order $order, float $percentage): Order
    {
        if (! $order->isClosed()) {
            throw new OrderNotClosedException();
        }

        // Verificar caja abierta
        $this->cashRegisterService->requireOpenRegister($order->restaurant_id);

        $order->update(['discount_percentage' => $percentage]);

        $this->recalculateTotals($order);

        $this->auditService->log(
            $order->restaurant_id, 'Order', $order->id,
            AuditService::ACTION_DISCOUNT_APPLIED,
            null,
            ['discount_percentage' => $percentage]
        );

        return $order->fresh();
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE
    |--------------------------------------------------------------------------
    */
    public function close(Order $order): Order
    {
        return DB::transaction(function () use ($order) {

            if (! $order->isOpen()) {
                throw new OrderNotOpenException();
            }

            if ($order->items()->count() === 0) {
                throw new EmptyOrderException();
            }

            $order->update([
                'status'    => Order::STATUS_CLOSED,
                'closed_at' => now(),
            ]);

            $this->auditService->log(
                $order->restaurant_id, 'Order', $order->id,
                AuditService::ACTION_CLOSED
            );

            return $order->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | REOPEN
    |--------------------------------------------------------------------------
    */
    public function reopen(Order $order): Order
    {
        return DB::transaction(function () use ($order) {

            if (! $order->isClosed()) {
                throw new OrderNotClosedException();
            }

            // Verificar caja abierta
            $this->cashRegisterService->requireOpenRegister($order->restaurant_id);

            $order->update([
                'status'    => Order::STATUS_OPEN,
                'closed_at' => null,
            ]);

            $this->auditService->log(
                $order->restaurant_id, 'Order', $order->id,
                AuditService::ACTION_UPDATED,
                ['status' => Order::STATUS_CLOSED],
                ['status' => Order::STATUS_OPEN]
            );

            return $order->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL
    |--------------------------------------------------------------------------
    */
    public function cancel(Order $order, string $reason): Order
    {
        return DB::transaction(function () use ($order, $reason) {

            if (! $order->isOpen()) {
                throw new OrderNotOpenException();
            }

            // Verificar caja abierta
            $this->cashRegisterService->requireOpenRegister($order->restaurant_id);

            $order->update([
                'status'              => Order::STATUS_CANCELLED,
                'cancelled_at'        => now(),
                'cancellation_reason' => $reason,
            ]);

            $this->auditService->log(
                $order->restaurant_id, 'Order', $order->id,
                AuditService::ACTION_CANCELLED,
                ['status' => Order::STATUS_OPEN],
                ['status' => Order::STATUS_CANCELLED, 'reason' => $reason]
            );

            return $order->fresh();
        });
    }

    /*
    |--------------------------------------------------------------------------
    | RECALCULATE TOTALS
    |--------------------------------------------------------------------------
    */
    public function recalculateTotals(Order $order): void
    {
        $subtotal = $order->items()->sum('subtotal');
        $discountPercentage = (float) $order->discount_percentage;
        $discountAmount = $subtotal * ($discountPercentage / 100);
        $total = $subtotal - $discountAmount;

        $order->update([
            'subtotal'        => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount'      => 0, // Precio ya incluye impuesto
            'total'           => max($total, 0),
        ]);
    }
}
