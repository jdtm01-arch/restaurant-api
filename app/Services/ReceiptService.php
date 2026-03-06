<?php

namespace App\Services;

use App\Models\Sale;

class ReceiptService
{
    private const CHANNEL_LABELS = [
        'dine_in'  => 'Salón',
        'takeaway' => 'Para llevar',
        'delivery' => 'Delivery',
    ];

    /**
     * Genera los datos estructurados del recibo para impresión/PDF.
     */
    public function generateReceiptData(Sale $sale): array
    {
        $sale->load([
            'order.items',
            'order.table',
            'user',
            'payments.paymentMethod',
            'restaurant',
        ]);

        $order = $sale->order;
        $restaurant = $sale->restaurant;

        return [
            'restaurant' => [
                'name'    => $restaurant->name,
                'address' => $restaurant->address,
                'ruc'     => $restaurant->ruc,
                'phone'   => $restaurant->phone,
            ],
            'receipt' => [
                'number'  => $sale->receipt_number,
                'date'    => $sale->paid_at->format('d/m/Y'),
                'time'    => $sale->paid_at->format('H:i'),
                'channel' => self::CHANNEL_LABELS[$sale->channel] ?? $sale->channel,
            ],
            'service' => [
                'table'  => $order->table ? "Mesa {$order->table->number}" : null,
                'waiter' => $sale->user?->name ?? 'N/A',
            ],
            'items' => $order->items->map(fn ($item) => [
                'quantity' => $item->quantity,
                'product'  => $item->product_name_snapshot,
                'price'    => $item->price_with_tax_snapshot,
                'subtotal' => $item->subtotal,
            ])->toArray(),
            'totals' => [
                'subtotal'        => $sale->subtotal,
                'discount_amount' => $sale->discount_amount,
                'tax_amount'      => $sale->tax_amount,
                'total'           => $sale->total,
            ],
            'payments' => $sale->payments->map(fn ($p) => [
                'method' => $p->paymentMethod->name,
                'amount' => $p->amount,
            ])->toArray(),
        ];
    }

    /**
     * Formatea el recibo en texto plano para impresora térmica.
     */
    public function formatForPrint(Sale $sale): string
    {
        $data  = $this->generateReceiptData($sale);
        $width = 40;

        $lines = [];
        $lines[] = str_repeat('=', $width);
        $lines[] = str_pad($data['restaurant']['name'], $width, ' ', STR_PAD_BOTH);

        if ($data['restaurant']['address']) {
            $lines[] = str_pad($data['restaurant']['address'], $width, ' ', STR_PAD_BOTH);
        }

        if ($data['restaurant']['ruc']) {
            $lines[] = str_pad("RUC: {$data['restaurant']['ruc']}", $width, ' ', STR_PAD_BOTH);
        }

        $lines[] = str_repeat('=', $width);
        $lines[] = str_pad('RECIBO DE PAGO', $width, ' ', STR_PAD_BOTH);
        $lines[] = str_pad("N° {$data['receipt']['number']}", $width, ' ', STR_PAD_BOTH);
        $lines[] = str_repeat('-', $width);
        $lines[] = "Fecha: {$data['receipt']['date']}  {$data['receipt']['time']}";

        $meta = [];
        if ($data['service']['table']) {
            $meta[] = $data['service']['table'];
        }
        $meta[] = "Canal: {$data['receipt']['channel']}";
        $lines[] = implode(' | ', $meta);
        $lines[] = "Atendido por: {$data['service']['waiter']}";
        $lines[] = str_repeat('-', $width);

        // Header
        $lines[] = sprintf("%-4s %-22s %10s", 'Cant', 'Producto', 'Precio');
        $lines[] = str_repeat('-', $width);

        foreach ($data['items'] as $item) {
            $lines[] = sprintf(
                "%4d  %-21s S/ %7s",
                $item['quantity'],
                mb_substr($item['product'], 0, 21),
                number_format($item['subtotal'], 2)
            );
        }

        $lines[] = str_repeat('-', $width);

        // Since prices include IGV (18%), break it out from the final total
        $totalFinal = (float) $data['totals']['total'];
        $baseAmount = $totalFinal / 1.18;   // amount without tax
        $igvAmount  = $totalFinal - $baseAmount;

        if ($data['totals']['discount_amount'] > 0) {
            $lines[] = sprintf("%-28s S/ %7s", 'Subtotal:', number_format($data['totals']['subtotal'], 2));
            $lines[] = sprintf("%-28s S/ %7s", 'Descuento:', number_format($data['totals']['discount_amount'], 2));
            $lines[] = str_repeat('-', $width);
        }

        $lines[] = sprintf("%-28s S/ %7s", 'OP. GRAVADA:', number_format($baseAmount, 2));
        $lines[] = sprintf("%-28s S/ %7s", 'IGV (18%):', number_format($igvAmount, 2));
        $lines[] = str_repeat('-', $width);
        $lines[] = sprintf("%-28s S/ %7s", 'TOTAL:', number_format($totalFinal, 2));
        $lines[] = str_repeat('-', $width);

        $lines[] = 'Pagos:';
        foreach ($data['payments'] as $payment) {
            $lines[] = sprintf("  %-26s S/ %7s", "{$payment['method']}:", number_format($payment['amount'], 2));
        }

        $lines[] = str_repeat('-', $width);
        $lines[] = str_pad('¡Gracias por su visita!', $width, ' ', STR_PAD_BOTH);
        $lines[] = str_repeat('=', $width);

        return implode("\n", $lines);
    }
}
