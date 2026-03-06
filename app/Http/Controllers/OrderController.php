<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddOrderItemRequest;
use App\Http\Requests\ApplyDiscountRequest;
use App\Http\Requests\CancelOrderRequest;
use App\Http\Requests\PayOrderRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderItemQuantityRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Table;
use App\Services\KitchenTicketService;
use App\Services\OrderService;
use App\Services\SaleService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    protected OrderService $orderService;
    protected KitchenTicketService $kitchenTicketService;
    protected SaleService $saleService;

    public function __construct(
        OrderService $orderService,
        KitchenTicketService $kitchenTicketService,
        SaleService $saleService
    ) {
        $this->orderService = $orderService;
        $this->kitchenTicketService = $kitchenTicketService;
        $this->saleService = $saleService;
    }

    /*
    |--------------------------------------------------------------------------
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [Order::class, $restaurantId]);

        $query = Order::with(['table', 'user', 'items'])
            ->where('restaurant_id', $restaurantId);

        // Filtros
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('channel')) {
            $query->where('channel', $request->channel);
        }

        if ($request->filled('table_id')) {
            $query->where('table_id', $request->table_id);
        }

        $orders = $query->orderByDesc('opened_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($orders);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */
    public function store(StoreOrderRequest $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('create', [Order::class, $restaurantId]);

        $order = $this->orderService->create([
            'restaurant_id' => $restaurantId,
            'channel'       => $request->channel,
            'table_id'      => $request->table_id,
        ]);

        // Add items
        foreach ($request->items as $itemData) {
            $this->orderService->addItem($order, $itemData);
        }

        $order->refresh()->load(['table', 'user', 'items']);

        return response()->json([
            'message' => 'Orden creada correctamente.',
            'data'    => $order,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['table', 'user', 'items']);

        return response()->json(['data' => $order]);
    }

    /*
    |--------------------------------------------------------------------------
    | ADD ITEM
    |--------------------------------------------------------------------------
    */
    public function addItem(AddOrderItemRequest $request, Order $order)
    {
        $this->authorize('update', $order);

        $item = $this->orderService->addItem($order, $request->validated());

        $order->refresh()->load('items');

        return response()->json([
            'message' => 'Ítem agregado correctamente.',
            'data'    => [
                'item'  => $item,
                'order' => $order,
            ],
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | REMOVE ITEM
    |--------------------------------------------------------------------------
    */
    public function removeItem(Order $order, OrderItem $item)
    {
        $this->authorize('update', $order);

        $this->orderService->removeItem($order, $item);

        $order->refresh()->load('items');

        return response()->json([
            'message' => 'Ítem eliminado correctamente.',
            'data'    => $order,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE ITEM QUANTITY
    |--------------------------------------------------------------------------
    */
    public function updateItemQuantity(UpdateOrderItemQuantityRequest $request, Order $order, OrderItem $item)
    {
        $this->authorize('update', $order);

        $updatedItem = $this->orderService->updateItemQuantity(
            $order,
            $item,
            $request->quantity,
        );

        $order->refresh()->load('items');

        return response()->json([
            'message' => 'Cantidad actualizada correctamente.',
            'data'    => [
                'item'  => $updatedItem,
                'order' => $order,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | APPLY DISCOUNT
    |--------------------------------------------------------------------------
    */
    public function applyDiscount(ApplyDiscountRequest $request, Order $order)
    {
        $this->authorize('applyDiscount', $order);

        $order = $this->orderService->applyDiscount($order, $request->discount_percentage);

        return response()->json([
            'message' => 'Descuento aplicado correctamente.',
            'data'    => $order,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CLOSE
    |--------------------------------------------------------------------------
    */
    public function close(Order $order)
    {
        $this->authorize('close', $order);

        $order = $this->orderService->close($order);

        return response()->json([
            'message' => 'Orden cerrada correctamente.',
            'data'    => $order,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | REOPEN
    |--------------------------------------------------------------------------
    */
    public function reopen(Order $order)
    {
        $this->authorize('close', $order);

        $order = $this->orderService->reopen($order);

        $order->load('items');

        return response()->json([
            'message' => 'Orden reabierta correctamente.',
            'data'    => $order,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CANCEL
    |--------------------------------------------------------------------------
    */
    public function cancel(CancelOrderRequest $request, Order $order)
    {
        $this->authorize('cancel', $order);

        $order = $this->orderService->cancel($order, $request->cancellation_reason);

        return response()->json([
            'message' => 'Orden cancelada correctamente.',
            'data'    => $order,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | KITCHEN TICKET
    |--------------------------------------------------------------------------
    */
    public function kitchenTicket(Order $order)
    {
        $this->authorize('kitchenTicket', $order);

        $ticketData = $this->kitchenTicketService->generateTicketData($order);
        $printFormat = $this->kitchenTicketService->formatForPrint($order);

        // Save current items as snapshot for future change tracking
        $this->kitchenTicketService->saveSnapshot($order);

        return response()->json([
            'data' => [
                'ticket' => $ticketData,
                'text'   => $printFormat,
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PAY
    |--------------------------------------------------------------------------
    */
    public function pay(PayOrderRequest $request, Order $order)
    {
        $this->authorize('pay', $order);

        $sale = $this->saleService->createFromOrder($order, $request->payments);

        $sale->load(['payments.paymentMethod']);

        return response()->json([
            'message' => 'Pago registrado correctamente.',
            'data'    => $sale,
        ], 201);
    }

    /*
    |--------------------------------------------------------------------------
    | BILL (Pre-payment account / Cuenta)
    |--------------------------------------------------------------------------
    */
    public function bill(Order $order)
    {
        $this->authorize('kitchenTicket', $order); // same permission as viewing

        $order->load(['items', 'table', 'user', 'restaurant']);

        $restaurant = $order->restaurant;
        $width = 40;
        $lines = [];

        $lines[] = str_repeat('=', $width);
        $lines[] = str_pad($restaurant->name ?? 'Restaurante', $width, ' ', STR_PAD_BOTH);

        if ($restaurant->address) {
            $lines[] = str_pad($restaurant->address, $width, ' ', STR_PAD_BOTH);
        }

        $lines[] = str_repeat('=', $width);
        $lines[] = str_pad('CUENTA', $width, ' ', STR_PAD_BOTH);
        $lines[] = str_repeat('-', $width);

        $channelLabels = ['dine_in' => 'Salón', 'takeaway' => 'Para llevar', 'delivery' => 'Delivery'];
        $lines[] = "Fecha: " . now()->format('d/m/Y H:i');
        $lines[] = "Pedido: #{$order->id}";

        if ($order->table) {
            $lines[] = "Mesa: {$order->table->name}";
        }

        $lines[] = "Canal: " . ($channelLabels[$order->channel] ?? $order->channel);
        $lines[] = "Atendido por: " . ($order->user->name ?? 'N/A');
        $lines[] = str_repeat('-', $width);

        $lines[] = sprintf("%-4s %-22s %10s", 'Cant', 'Producto', 'Precio');
        $lines[] = str_repeat('-', $width);

        foreach ($order->items as $item) {
            $lines[] = sprintf(
                "%4d  %-21s S/ %7s",
                $item->quantity,
                mb_substr($item->product_name_snapshot ?? 'Producto', 0, 21),
                number_format($item->subtotal, 2)
            );
        }

        $lines[] = str_repeat('-', $width);

        $subtotal = (float) $order->subtotal;
        $total = (float) $order->total;

        if ($order->discount_percentage > 0) {
            $lines[] = sprintf("%-28s S/ %7s", 'Subtotal:', number_format($subtotal, 2));
            $lines[] = sprintf("%-28s S/ %7s", "Descuento ({$order->discount_percentage}%):", number_format($subtotal - $total, 2));
            $lines[] = str_repeat('-', $width);
        }

        $lines[] = sprintf("%-28s S/ %7s", 'TOTAL:', number_format($total, 2));
        $lines[] = str_repeat('-', $width);
        $lines[] = str_pad('*** CUENTA - NO ES COMPROBANTE ***', $width, ' ', STR_PAD_BOTH);
        $lines[] = str_pad('¡Gracias por su visita!', $width, ' ', STR_PAD_BOTH);

        return response()->json([
            'data' => [
                'text' => implode("\n", $lines),
            ],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CHANGE TABLE
    |--------------------------------------------------------------------------
    */
    public function changeTable(Request $request, Order $order)
    {
        $this->authorize('update', $order);

        if (! in_array($order->status, ['open', 'closed'])) {
            return response()->json([
                'message' => 'Solo se puede cambiar la mesa de pedidos abiertos o cerrados.',
            ], 422);
        }

        $request->validate([
            'table_id' => 'required|integer|exists:tables,id',
        ]);

        $restaurantId = $request->header('X-Restaurant-Id');
        $newTable = Table::where('id', $request->table_id)
            ->where('restaurant_id', $restaurantId)
            ->firstOrFail();

        // Check that the target table is not occupied by another open/closed order
        $occupying = Order::where('table_id', $newTable->id)
            ->where('restaurant_id', $restaurantId)
            ->whereIn('status', ['open', 'closed'])
            ->where('id', '!=', $order->id)
            ->first();

        if ($occupying) {
            return response()->json([
                'message' => "La mesa {$newTable->name} ya está ocupada por el pedido #{$occupying->id}.",
            ], 422);
        }

        $oldTableId = $order->table_id;
        $order->table_id = $newTable->id;
        $order->save();
        $order->load(['table', 'user', 'items']);

        return response()->json([
            'message' => "Mesa cambiada a {$newTable->name} exitosamente.",
            'data'    => $order,
            'meta'    => [
                'old_table_id' => $oldTableId,
                'new_table_id' => $newTable->id,
            ],
        ]);
    }
}
