<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // luego podemos integrar roles
    }

    public function rules(): array
    {
        $restaurantId = $this->get('restaurant_id');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('product_categories')
                    ->where(fn ($query) =>
                        $query->where('restaurant_id', $restaurantId)
                    )
            ],
        ];
    }
}