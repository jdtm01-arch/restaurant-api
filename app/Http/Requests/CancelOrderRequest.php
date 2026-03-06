<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'El motivo de cancelación es obligatorio.',
            'cancellation_reason.min'      => 'El motivo debe tener al menos 5 caracteres.',
            'cancellation_reason.max'      => 'El motivo no puede tener más de 500 caracteres.',
        ];
    }
}
