<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

#[BodyParameter('name', type: 'string', required: false, description: 'Новое наименование предмета.', example: 'Гвозди')]
#[BodyParameter('description', type: 'string|null', required: false, description: 'Новое дополнительное описание (передайте null, чтобы удалить описание).', example: 'Хранить исключительно в сухом месте')]
#[BodyParameter('quantity', type: 'integer', required: false, description: 'Новое количество едениц предмета.', example: 2)]
class InventoryUpdateRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'string', 'max:255', 'nullable'],
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:5000'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->missing('name') && $this->missing('description') && $this->missing('quantity')) {
                    $validator->errors()->add('request', 'Должно быть передано хотя бы одно поле');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Наименование предмета должно быть строкой.',
            'name.max' => 'Наименование предмета не должно превышать 255 символов.',

            'description.string' => 'Дополнительное описание должно быть строкой.',
            'description.max' => 'Дополнительное описание не должно превышать 255 символов.',

            'quantity.integer' => 'Количество едениц предмета должно быть целым числом.',
            'quantity.min' => 'Количество едениц предмета должно быть не меньше еденицы.',
            'quantity.max' => 'Количество едениц предмета не может быть больше 5000.',
        ];
    }
}
