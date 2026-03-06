<?php

namespace App\Services;

use App\Models\Order;

class KitchenTicketService
{
    private const CHANNEL_LABELS = [
        'dine_in'  => 'Salón',
        'takeaway' => 'Para llevar',
        'delivery' => 'Delivery',
    ];

    /**
     * Genera los datos del ticket de cocina para una orden.
     */
    public function generateTicketData(Order $order): array
    {
        $order->load(['items', 'table', 'user']);

        /** @var \App\Models\Table|null $table */
        $table = $order->getRelationValue('table');

        $currentItems = $order->items->map(fn ($item) => [
            'product'    => $item->product_name_snapshot,
            'product_id' => $item->product_id,
            'quantity'   => $item->quantity,
            'notes'      => $item->notes,
        ])->toArray();

        $data = [
            'order_id'   => $order->id,
            'channel'    => self::CHANNEL_LABELS[$order->channel] ?? $order->channel,
            'table'      => $table ? "Mesa {$table->name}" : null,
            'waiter'     => $order->user?->name ?? 'N/A',
            'opened_at'  => $order->opened_at?->format('Y-m-d H:i:s'),
            'items'      => $currentItems,
            'changes'    => null,
        ];

        // Compute changes if there's a previous snapshot (order was reopened)
        $previousSnapshot = $order->comanda_snapshot;

        if (! empty($previousSnapshot)) {
            $data['changes'] = $this->computeChanges($previousSnapshot, $currentItems);
        }

        return $data;
    }

    /**
     * Compute the diff between a previous snapshot and the current items.
     */
    private function computeChanges(array $previous, array $current): array
    {
        // Build indexed maps by product_id => { product, quantity }
        $prevMap = [];
        foreach ($previous as $item) {
            $pid = $item['product_id'];
            $prevMap[$pid] = [
                'product'  => $item['product'],
                'quantity' => $item['quantity'],
            ];
        }

        $currMap = [];
        foreach ($current as $item) {
            $pid = $item['product_id'];
            $currMap[$pid] = [
                'product'  => $item['product'],
                'quantity' => $item['quantity'],
            ];
        }

        $added    = []; // Items that are new or have increased quantity
        $removed  = []; // Items that were removed or have decreased quantity
        $modified = []; // Items whose quantity changed

        // Check for added / modified
        foreach ($currMap as $pid => $curr) {
            if (! isset($prevMap[$pid])) {
                // Entirely new item
                $added[] = [
                    'product'  => $curr['product'],
                    'quantity' => $curr['quantity'],
                ];
            } elseif ($curr['quantity'] !== $prevMap[$pid]['quantity']) {
                $diff = $curr['quantity'] - $prevMap[$pid]['quantity'];
                $modified[] = [
                    'product'      => $curr['product'],
                    'old_quantity' => $prevMap[$pid]['quantity'],
                    'new_quantity' => $curr['quantity'],
                    'diff'         => $diff,
                ];
            }
        }

        // Check for removed
        foreach ($prevMap as $pid => $prev) {
            if (! isset($currMap[$pid])) {
                $removed[] = [
                    'product'  => $prev['product'],
                    'quantity' => $prev['quantity'],
                ];
            }
        }

        $hasChanges = count($added) > 0 || count($removed) > 0 || count($modified) > 0;

        return [
            'has_changes' => $hasChanges,
            'added'       => $added,
            'removed'     => $removed,
            'modified'    => $modified,
        ];
    }

    /**
     * Saves the current order items as the comanda snapshot for future diff.
     */
    public function saveSnapshot(Order $order): void
    {
        $order->load('items');

        $snapshot = $order->items->map(fn ($item) => [
            'product_id' => $item->product_id,
            'product'    => $item->product_name_snapshot,
            'quantity'   => $item->quantity,
        ])->toArray();

        $order->update(['comanda_snapshot' => $snapshot]);
    }

    /**
     * Formatea los datos del ticket de cocina para impresión texto plano.
     */
    public function formatForPrint(Order $order): string
    {
        $data  = $this->generateTicketData($order);
        $width = 40;

        $lines = [];
        $lines[] = str_repeat('=', $width);
        $lines[] = str_pad('COMANDA', $width, ' ', STR_PAD_BOTH);
        $lines[] = str_repeat('=', $width);
        $lines[] = "Orden #: {$data['order_id']}";
        $lines[] = "Canal:   {$data['channel']}";

        if ($data['table']) {
            $lines[] = "Mesa:    {$data['table']}";
        }

        $lines[] = "Mozo:    {$data['waiter']}";
        $lines[] = "Hora:    {$data['opened_at']}";
        $lines[] = str_repeat('-', $width);

        foreach ($data['items'] as $item) {
            $lines[] = "{$item['quantity']}x {$item['product']}";
            if (! empty($item['notes'])) {
                $lines[] = "   >> {$item['notes']}";
            }
        }

        // Print changes section if order was modified after reopen
        $changes = $data['changes'] ?? null;
        if ($changes && $changes['has_changes']) {
            $lines[] = str_repeat('=', $width);
            $lines[] = str_pad('CAMBIOS REALIZADOS', $width, ' ', STR_PAD_BOTH);
            $lines[] = str_repeat('=', $width);

            if (! empty($changes['added'])) {
                foreach ($changes['added'] as $item) {
                    $lines[] = "+ NUEVO: {$item['quantity']}x {$item['product']}";
                }
            }

            if (! empty($changes['removed'])) {
                foreach ($changes['removed'] as $item) {
                    $lines[] = "- RETIRADO: {$item['quantity']}x {$item['product']}";
                }
            }

            if (! empty($changes['modified'])) {
                foreach ($changes['modified'] as $item) {
                    $sign = $item['diff'] > 0 ? '+' : '';
                    $lines[] = "* {$item['product']}: {$item['old_quantity']} -> {$item['new_quantity']} ({$sign}{$item['diff']})";
                }
            }
        }

        $lines[] = str_repeat('=', $width);

        return implode("\n", $lines);
    }
}
