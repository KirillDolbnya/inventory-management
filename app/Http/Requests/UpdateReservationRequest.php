<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('quantity', type: 'integer', description: 'Новое количество резервируемого инвентаря', example: 5, required: true)]
class UpdateReservationRequest extends FormRequest
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
            'quantity' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'Количество резервируемого инвентаря обязательно для заполнения.',
            'quantity.integer' => 'Количество инвентаря должно быть целым числом.',
            'quantity.min' => 'Количество инвентаря в резерве должно быть не менее 1 шт.',
        ];
    }
}
