<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel'              => ['required', 'in:' . Order::CHANNEL_DINE_IN . ',' . Order::CHANNEL_TAKEAWAY . ',' . Order::CHANNEL_DELIVERY],
            'table_id'             => ['nullable', 'integer', 'exists:tables,id'],
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_id'   => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.notes'        => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'channel.required'          => 'El canal es obligatorio.',
            'channel.in'                => 'El canal debe ser dine_in, takeaway o delivery.',
            'table_id.exists'           => 'La mesa seleccionada no existe.',
            'items.required'            => 'La orden debe tener al menos un ítem.',
            'items.min'                 => 'La orden debe tener al menos un ítem.',
            'items.*.product_id.required' => 'Cada ítem debe tener un producto.',
            'items.*.product_id.exists' => 'El producto seleccionado no existe.',
            'items.*.quantity.required' => 'Cada ítem debe tener cantidad.',
            'items.*.quantity.min'      => 'La cantidad mínima es 1.',
        ];
    }
}
