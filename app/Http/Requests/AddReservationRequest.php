<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('reservations', type: 'array', description: 'Список ячеек хранения и количества товаров для резервирования')]
#[BodyParameter('reservations.*.inventory_placement_id', type: 'integer', description: 'Уникальный ID размещения инвентаря на складе', example: 142)]
#[BodyParameter('reservations.*.quantity', type: 'integer', description: 'Количество резервируемых единиц товара', example: 25)]
class AddReservationRequest extends FormRequest
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
            'reservations' => ['required', 'list', 'min:1'],
            'reservations.*.inventory_placement_id' => ['required', 'integer', 'min:1', 'distinct'],
            'reservations.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'reservations.required' => 'Список резервируемых позиций обязателен.',
            'reservations.list' => 'Список позиций должен быть последовательным массивом.',
            'reservations.min' => 'В резерве должна быть указана минимум одна позиция.',

            'reservations.*.inventory_placement_id.required' => 'ID размещения ячейки обязательно для заполнения.',
            'reservations.*.inventory_placement_id.integer' => 'ID размещения должно быть целым числом.',
            'reservations.*.inventory_placement_id.min' => 'Неверный ID размещения ячейки.',
            'reservations.*.inventory_placement_id.distinct' => 'В списке позиций обнаружены дубликаты одинаковых ячеек.',

            'reservations.*.quantity.required' => 'Количество предмета обязательно для заполнения.',
            'reservations.*.quantity.integer' => 'Количество предмета должно быть целым числом.',
            'reservations.*.quantity.min' => 'Количество предмета не должно быть меньше 1',
        ];
    }
}
