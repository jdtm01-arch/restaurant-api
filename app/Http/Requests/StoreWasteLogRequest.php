<?php

namespace App\Http\Requests;

use App\Models\WasteLog;
use Illuminate\Foundation\Http\FormRequest;

class StoreWasteLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'     => ['nullable', 'integer', 'exists:products,id'],
            'description'    => ['nullable', 'string', 'max:255'],
            'quantity'       => ['required', 'numeric', 'min:0.01'],
            'unit'           => ['nullable', 'string', 'max:20'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'waste_date'     => ['required', 'date'],
            'reason'         => ['nullable', 'string', 'in:' . implode(',', WasteLog::VALID_REASONS)],
        ];
    }
}
