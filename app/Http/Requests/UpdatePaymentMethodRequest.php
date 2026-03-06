<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentMethodId = $this->route('payment_method');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('payment_methods', 'name')
                    ->ignore($paymentMethodId),
            ],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
