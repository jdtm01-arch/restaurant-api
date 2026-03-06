<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PerformCashClosingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.required'        => 'La fecha es obligatoria.',
            'date.date'            => 'La fecha debe ser una fecha válida.',
            'date.before_or_equal' => 'La fecha no puede ser futura.',
        ];
    }
}
