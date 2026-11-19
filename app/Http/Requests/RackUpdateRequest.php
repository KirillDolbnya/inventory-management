<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('code', type: 'string', required: false, description: 'Уникальный код стеллажа (автоматически переводится в верхний регистр)', example: 'ST-01')]
#[BodyParameter('levels_count', type: 'integer', required: false, description: 'Количество уровней (полоки) стеллажа от 1 до 10', example: 2)]
#[BodyParameter('cells_per_level', type: 'integer', required: false, description: 'Количество ячеек на одном уровне от 1 до 10', example: 4)]
class RackUpdateRequest extends FormRequest
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
            'code' => ['sometimes', 'string', 'max:20', 'distinct:strict', 'uppercase'],
            'levels_count' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'cells_per_level' => ['sometimes', 'integer', 'min:1', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.string' => 'Код стеллажа должен быть текстовой строкой.',
            'code.max' => 'Код стеллажа не должен превышать 20 символов.',
            'code.distinct' => 'Коды стеллажей в запросе не должны дублироваться.',

            'levels_count.integer' => 'Количество уровней должно быть целым числом.',
            'levels_count.min' => 'Стеллаж должен иметь как минимум :min уровень.',
            'levels_count.max' => 'Количество уровней не может превышать :max.',

            'cells_per_level.integer' => 'Количество ячеек на уровне должно быть целым числом.',
            'cells_per_level.min' => 'На уровне должна быть как минимум :min ячейка.',
            'cells_per_level.max' => 'Количество ячеек на уровне не может превышать :max.',
        ];
    }
}
