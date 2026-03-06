<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSupplierRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $supplierId = $this->route('supplier');

        return [
            'name' => 'required|string|max:150',
            'ruc' => 'required|string|max:20|unique:suppliers,ruc,' . $this->route('supplier')->id,
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:150',
            'contact_person' => 'nullable|string|max:150',
            'description' => 'nullable|string',
        ];
    }
}
