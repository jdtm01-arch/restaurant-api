<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payments'                         => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id'     => ['required', 'integer', 'exists:payment_methods,id'],
            'payments.*.financial_account_id'  => ['required', 'integer', 'exists:financial_accounts,id'],
            'payments.*.amount'                => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'payments.required'                     => 'Debe incluir al menos un pago.',
            'payments.min'                          => 'Debe incluir al menos un pago.',
            'payments.*.payment_method_id.required' => 'El método de pago es obligatorio.',
            'payments.*.payment_method_id.exists'   => 'El método de pago seleccionado no existe.',
            'payments.*.amount.required'            => 'El monto del pago es obligatorio.',
            'payments.*.amount.min'                 => 'El monto mínimo de cada pago es 0.01.',
            'payments.*.financial_account_id.required' => 'La cuenta financiera es obligatoria.',
            'payments.*.financial_account_id.exists'   => 'La cuenta financiera seleccionada no existe.',
        ];
    }
}
