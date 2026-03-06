<?php

namespace App\Services;

use App\Exceptions\Sale\OrderAlreadyPaidException;
use App\Exceptions\Sale\OrderNotClosedException;
use App\Exceptions\Sale\PaymentSumMismatchException;
use App\Models\Order;
use App\Models\Sale;
use App\Models\SalePayment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaleService
{
    protected CashRegisterService $cashRegisterService;
    protected AuditService $auditService;
    protected FinancialMovementService $financialMovementService;
    protected CashValidationService $cashValidation;

    public function __construct(
        CashRegisterService $cashRegisterService,
        AuditService $auditService,
        FinancialMovementService $financialMovementService,
        CashValidationService $cashValidation
    ) {
        $this->cashRegisterService = $cashRegisterService;
        $this->auditService = $auditService;
        $this->financialMovementService = $financialMovementService;
        $this->cashValidation = $cashValidation;
    }

    /**
     * Genera una venta desde una orden cerrada y registra los pagos.
     */
    public function createFromOrder(Order $order, array $payments): Sale
    {
        return DB::transaction(function () use ($order, $payments) {

            // 1. La orden no debe tener ya una venta asociada (o ya estar pagada)
            if ($order->status === Order::STATUS_PAID || $order->sale()->exists()) {
                throw new OrderAlreadyPaidException();
            }

            // 2. La orden debe estar cerrada
            if (! $order->isClosed()) {
                throw new OrderNotClosedException();
            }

            // 3. Verificar que no exista cierre contable para hoy
            $today = now()->toDateString();
            if ($this->cashValidation->hasClosing($order->restaurant_id, $today)) {
                throw ValidationException::withMessages([
                    'sale' => ['No se puede registrar una venta: ya existe cierre contable para la fecha de hoy.'],
                ]);
            }

            // 4. Caja abierta
            $cashRegister = $this->cashRegisterService->requireOpenRegister($order->restaurant_id);

            // 5. Validar suma de pagos
            $paymentSum = collect($payments)->sum('amount');
            if (round($paymentSum, 2) !== round((float) $order->total, 2)) {
                throw new PaymentSumMismatchException();
            }

            // 6. Crear la venta
            $sale = Sale::create([
                'restaurant_id'    => $order->restaurant_id,
                'order_id'         => $order->id,
                'cash_register_id' => $cashRegister->id,
                'user_id'          => Auth::id(),
                'channel'          => $order->channel,
                'subtotal'         => $order->subtotal,
                'discount_amount'  => $order->discount_amount,
                'tax_amount'       => $order->tax_amount,
                'total'            => $order->total,
                'receipt_number'   => $this->generateReceiptNumber($order->restaurant_id),
                'paid_at'          => now(),
            ]);

            // 7. Crear los pagos y movimientos financieros
            foreach ($payments as $payment) {
                $salePayment = SalePayment::create([
                    'sale_id'              => $sale->id,
                    'payment_method_id'    => $payment['payment_method_id'],
                    'financial_account_id' => $payment['financial_account_id'] ?? null,
                    'amount'               => $payment['amount'],
                    'created_at'           => now(),
                ]);

                // Generar movimiento financiero si hay cuenta asignada
                $this->financialMovementService->createForSalePayment(
                    $salePayment,
                    $order->restaurant_id,
                    now()->toDateString()
                );
            }

            $sale->load('payments.paymentMethod');

            // 8. Mark order as paid
            $order->update(['status' => Order::STATUS_PAID]);

            $this->auditService->log(
                $order->restaurant_id, 'Sale', $sale->id,
                AuditService::ACTION_CREATED,
                null,
                ['order_id' => $order->id, 'total' => $sale->total, 'receipt_number' => $sale->receipt_number]
            );

            return $sale;
        });
    }

    /**
     * Genera número correlativo de recibo: R-{restaurant_id}-{YYYYMMDD}-{correlativo}.
     */
    public function generateReceiptNumber(int $restaurantId): string
    {
        $today = now()->format('Ymd');
        $prefix = "R-{$restaurantId}-{$today}-";

        $lastReceipt = Sale::where('restaurant_id', $restaurantId)
            ->where('receipt_number', 'like', $prefix . '%')
            ->orderByDesc('receipt_number')
            ->value('receipt_number');

        if ($lastReceipt) {
            $lastNumber = (int) str_replace($prefix, '', $lastReceipt);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return $prefix . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
}
