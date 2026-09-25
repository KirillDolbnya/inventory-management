<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('name', type: 'string', required: true, description: 'Название объекта', example: 'Центральный офис')]
#[BodyParameter('fias_id', type: 'string', required: true, description: 'Идентификатор адреса в провайдере DaData', example: '8ed1481e-1f9e-4340-9774-325db197bf5d')]
class WarehouseCreateRequest extends FormRequest
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
            'fias_id' => ['required', 'string', 'uuid'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Пожалуйста, введите название.',
            'name.string' => 'Название должно быть строкой.',
            'name.max' => 'Название превышает максимальное допустимое количество символов.',

            'fias_id.required' => 'Пожалуйста, укажите ФИАС ID адреса.',
            'fias_id.string' => 'ФИАС ID адреса должен быть строкой.',
            'fias_id.uuid' => 'ФИАС ID адреса имеет неверный формат UUID.',
        ];
    }
}
