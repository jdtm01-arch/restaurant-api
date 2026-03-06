<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $restaurantId = $this->get('restaurant_id');
        $productId = $this->route('product')->id;

        return [
            'name' => ['required', 'string', 'max:150',
                Rule::unique('products')->where(fn($q) => $q->where('restaurant_id', $restaurantId))->ignore($productId)
            ],
            'category_id' => ['required', 'integer', Rule::exists('product_categories', 'id')->where(fn($q) => $q->where('restaurant_id', $restaurantId))],
            'description' => ['nullable', 'string', 'max:500'],
            'price_with_tax' => ['required', 'numeric', 'min:0'],
            'image' => ['nullable', 'image', 'max:150'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
