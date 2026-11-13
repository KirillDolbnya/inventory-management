<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('racks', type: 'array', required: true, description: 'Массив стеллажей для массового добавления на склад')]
#[BodyParameter('racks.*.code', type: 'string', required: true, description: 'Уникальный код/номер стеллажа в рамках запроса', example: 'A')]
#[BodyParameter('racks.*.levels_count', type: 'integer', required: true, description: 'Количество уровней (ярусов) у стеллажа', example: 4)]
#[BodyParameter('racks.*.cells_per_level', type: 'integer', required: true, description: 'Количество ячеек на одном уровне', example: 5)]
class RackCreateRequest extends FormRequest
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
            'racks' => ['required', 'array', 'list', 'min:1', 'max:10'],

            'racks.*' => ['required', 'array:code,levels_count,cells_per_level'],

            'racks.*.code' => ['required', 'string', 'max:20', 'distinct:strict', 'uppercase'],
            'racks.*.levels_count' => ['required', 'integer', 'min:1', 'max:10'],
            'racks.*.cells_per_level' => ['required', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'racks.required' => 'Массив стеллажей обязателен для заполнения.',
            'racks.array' => 'Переданные данные стеллажей должны быть массивом.',
            'racks.list' => 'Стеллажи должны быть переданы списком.',
            'racks.min' => 'Необходимо передать хотя бы один стеллаж.',
            'racks.max' => 'Нельзя за один запрос создать более 10 стеллажей.',

            'racks.*.required' => 'Данные стеллажа обязательны.',
            'racks.*.array' => 'Каждый элемент в массиве racks должен быть объектом с ключами: code, levels_count, cells_per_level.',

            'racks.*.code.required' => 'Пожалуйста, укажите код стеллажа.',
            'racks.*.code.string' => 'Код стеллажа должен быть строкой.',
            'racks.*.code.max' => 'Код стеллажа не должен превышать 20 символов.',
            'racks.*.code.distinct' => 'Коды стеллажей в одном запросе не должны повторяться.',
            'racks.*.code.uppercase' => 'Код стеллажа должен быть написан заглавными буквами.',

            'racks.*.levels_count.required' => 'Укажите количество уровней для стеллажа.',
            'racks.*.levels_count.integer' => 'Количество уровней должно быть целым числом.',
            'racks.*.levels_count.min' => 'Количество уровней должно быть не менее 1.',
            'racks.*.levels_count.max' => 'Количество уровней не должно превышать 10.',

            'racks.*.cells_per_level.required' => 'Укажите количество ячеек на уровень.',
            'racks.*.cells_per_level.integer' => 'Количество ячеек должно быть целым числом.',
            'racks.*.cells_per_level.min' => 'Количество ячеек на уровень должно быть не менее 1.',
            'racks.*.cells_per_level.max' => 'Количество ячеек на уровень не должно превышать 10.',
        ];
    }
}
