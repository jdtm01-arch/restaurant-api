<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $restaurantId = $this->get('restaurant_id');

        return [
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('expense_categories')
                    ->where(fn ($query) =>
                        $query->where('restaurant_id', $restaurantId)
                    )
            ],
        ];
    }
}
