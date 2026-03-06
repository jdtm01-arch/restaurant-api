<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentMethodRequest;
use App\Http\Requests\UpdatePaymentMethodRequest;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class PaymentMethodController extends Controller
{
    /**
     * GET /payment-methods — Lista de métodos de pago.
     */
    public function index(Request $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('viewAny', [PaymentMethod::class, $restaurantId]);

        $methods = PaymentMethod::orderBy('name')->get();

        return response()->json(['data' => $methods]);
    }

    /**
     * GET /payment-methods/{payment_method} — Detalle de método de pago.
     */
    public function show(PaymentMethod $payment_method)
    {
        return response()->json(['data' => $payment_method]);
    }

    /**
     * POST /payment-methods — Crear método de pago.
     */
    public function store(StorePaymentMethodRequest $request)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('create', [PaymentMethod::class, $restaurantId]);

        $method = PaymentMethod::create([
            'name' => $request->name,
        ]);

        return response()->json([
            'message' => 'Método de pago creado correctamente',
            'data'    => $method,
        ], 201);
    }

    /**
     * PUT /payment-methods/{payment_method} — Actualizar método de pago.
     */
    public function update(
        UpdatePaymentMethodRequest $request,
        PaymentMethod $payment_method
    ) {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('update', [$payment_method, $restaurantId]);

        $payment_method->update($request->only(['name', 'active']));

        return response()->json([
            'message' => 'Método de pago actualizado correctamente',
            'data'    => $payment_method,
        ]);
    }

    /**
     * DELETE /payment-methods/{payment_method} — Eliminar método de pago.
     */
    public function destroy(Request $request, PaymentMethod $payment_method)
    {
        $restaurantId = $request->get('restaurant_id');
        $this->authorize('delete', [$payment_method, $restaurantId]);

        // Prevent deletion if has associated payments
        if ($payment_method->payments()->exists() || $payment_method->salePayments()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar el método de pago porque tiene pagos asociados.',
            ], 422);
        }

        $payment_method->delete();

        return response()->json([
            'message' => 'Método de pago eliminado correctamente',
        ]);
    }
}
