<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpensePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_method_id'    => 'required|exists:payment_methods,id',
            'financial_account_id' => 'required|integer|exists:financial_accounts,id',
            'amount'               => 'required|numeric|min:0.01',
            'paid_at'              => 'required|date',
        ];
    }
}
