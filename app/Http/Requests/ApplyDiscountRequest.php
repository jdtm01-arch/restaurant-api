<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApplyDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'discount_percentage.required' => 'El porcentaje de descuento es obligatorio.',
            'discount_percentage.min'      => 'El porcentaje mínimo es 0.',
            'discount_percentage.max'      => 'El porcentaje máximo es 100.',
        ];
    }
}
