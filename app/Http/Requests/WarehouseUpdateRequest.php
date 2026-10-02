<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('name', type: 'string', description: 'Название объекта', example: 'Центральный офис 2')]
#[BodyParameter('fias_id', type: 'string', description: 'Идентификатор адреса в провайдере DaData', example: 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e')]
class WarehouseUpdateRequest extends FormRequest
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
            'name' => ['string', 'max:255', 'required_without_all:fias_id'],
            'fias_id' => ['string', 'uuid', 'required_without_all:name'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required_without_all' => 'Передайте хотя бы один параметр для обновления (название или ФИАС ID).',
            'name.string' => 'Название должно быть строкой.',
            'name.max' => 'Название превышает максимальное допустимое количество символов.',

            'fias_id.required_without_all' => 'Передайте хотя бы один параметр для обновления (название или ФИАС ID).',
            'fias_id.string' => 'ФИАС ID адреса должен быть строкой.',
            'fias_id.uuid' => 'ФИАС ID адреса имеет неверный формат UUID.',
        ];
    }
}
