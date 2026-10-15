<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class InventoryTransferRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'target_cell_id' => ['required', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_cell_id.required' => 'Идентификатор целевой ячейки обязателен для заполнения.',
            'target_cell_id.integer' => 'Идентификатор целевой ячейки должен быть целым числом.',
            'target_cell_id.min' => 'Идентификатор целевой ячейки должен быть не менее 1.',

            'quantity.required' => 'Количество предмета обязательно для заполнения.',
            'quantity.integer' => 'Количество предмета должно быть целым числом.',
            'quantity.min' => 'Количество предмета должно быть не менее 1 шт.',
        ];
    }
}
