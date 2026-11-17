<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('name', type: 'string', description: 'Название бронирования/операции', example: 'Резерв под заказ клиента №208')]
#[BodyParameter('description', type: 'string|null', description: 'Дополнительные примечания к резерву (необязательно)', example: 'Отгрузка приоритетной транспортной компанией')]
#[BodyParameter('starts_at', type: 'string', description: 'Дата начала в формате ISO 8601', example: '2026-10-18T09:00:00+03:00')]
#[BodyParameter('ends_at', type: 'string', description: 'Дата окончания в формате ISO 8601', example: '2026-10-25T18:00:00+03:00')]
#[BodyParameter('reservations', type: 'array', description: 'Список ячеек хранения и количества товаров для резервирования')]
#[BodyParameter('reservations.*.inventory_placement_id', type: 'integer', description: 'Уникальный ID размещения инвентаря на складе', example: 142)]
#[BodyParameter('reservations.*.quantity', type: 'integer', description: 'Количество резервируемых единиц товара', example: 25)]
class EventCreateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:255', 'nullable'],
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after_or_equal:now'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i:sP', 'after:starts_at'],
            'reservations' => ['required', 'list', 'min:1'],
            'reservations.*.inventory_placement_id' => ['required', 'integer', 'min:1', 'distinct'],
            'reservations.*.quantity' => ['required', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Название резерва обязательно для заполнения.',
            'name.string' => 'Название резерва должно быть строкой.',
            'name.max' => 'Название резерва не должно превышать 255 символов.',

            'description.string' => 'Описание должно быть текстовой строкой.',
            'description.max' => 'Описание не должно превышать 255 символов.',

            'starts_at.required' => 'Дата начала резервирования обязательна.',
            'starts_at.date_format' => 'Указан неверный формат даты начала. Ожидается ISO 8601 (например, 2026-10-18T09:00:00+03:00).',
            'starts_at.after_or_equal' => 'Дата начала резервирования не может быть в прошлом.',

            'ends_at.required' => 'Дата окончания резервирования обязательна.',
            'ends_at.date_format' => 'Указан неверный формат даты окончания. Ожидается ISO 8601 (например, 2026-10-25T18:00:00+03:00).',
            'ends_at.after' => 'Дата окончания должна быть строго позже даты начала.',

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
