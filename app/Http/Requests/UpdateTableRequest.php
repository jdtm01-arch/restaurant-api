<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $restaurantId = $this->get('restaurant_id');
        $tableId = $this->route('table');

        return [
            'number' => [
                'sometimes',
                'required',
                'integer',
                'min:1',
                Rule::unique('tables')
                    ->where(fn ($query) =>
                        $query->where('restaurant_id', $restaurantId)
                    )
                    ->ignore($tableId),
            ],
            'name' => 'nullable|string|max:100',
            'is_active' => 'sometimes|boolean',
        ];
    }
}
