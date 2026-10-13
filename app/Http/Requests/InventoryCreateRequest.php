<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('name', type: 'string', required: true, description: 'Наименование предмета', example: 'Кабель RJ-45')]
#[BodyParameter('description', type: 'string', required: false, description: 'Дополнительное описание или примечание к инвентарной единице', example: 'Хранить в сухом месте')]
#[BodyParameter('quantity', type: 'integer', required: true, description: 'Количество единиц предмета', example: 10)]
#[BodyParameter('cell_id', type: 'integer', required: false, description: 'Идентификатор ячейки склада, в которую размещается предмет', example: 1)]
class InventoryCreateRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:1'],
            'cell_id' => ['sometimes', 'integer', 'min:1', 'nullable'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Пожалуйста, укажите наименование предмета.',
            'name.string' => 'Наименование предмета должно быть строкой.',
            'name.max' => 'Наименование предмета не должно превышать 255 символов.',

            'description.string' => 'Дополнительное описание должно быть строкой.',
            'description.max' => 'Дополнительное описание не должно превышать 255 символов.',

            'quantity.required' => 'Пожалуйста, укажите количество единиц предмета.',
            'quantity.integer' => 'Количество единиц предмета должно быть целым числом.',
            'quantity.min' => 'Количество единиц предмета должно быть не менее 1.',

            'cell_id.integer' => 'Идентификатор ячейки должен быть целым числом.',
            'cell_id.min' => 'Идентификатор ячейки должен быть не менее 1.',
        ];
    }
}
