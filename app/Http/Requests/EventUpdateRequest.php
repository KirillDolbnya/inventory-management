<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('name', type: 'string', description: 'Название бронирования/операции', example: 'Резерв под заказ клиента №208', required: false)]
#[BodyParameter('description', type: 'string', description: 'Дополнительное описание операции', example: 'Срочный резерв, заберут в пятницу', required: false)]
#[BodyParameter('starts_at', type: 'string', description: 'Дата и время начала в формате ISO 8601', example: '2026-11-05T10:00:00+03:00', required: false)]
#[BodyParameter('ends_at', type: 'string', description: 'Дата и время окончания в формате ISO 8601', example: '2026-11-05T11:00:00+03:00', required: false)]
class EventUpdateRequest extends FormRequest
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
            'starts_at' => ['sometimes', 'date_format:Y-m-d\TH:i:sP', 'after_or_equal:now'],
            'ends_at' => ['sometimes', 'date_format:Y-m-d\TH:i:sP'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Название бронирования должно быть строкой.',
            'name.max' => 'Название бронирования не должно превышать 255 символов.',

            'description.string' => 'Описание должно быть текстовой строкой.',
            'description.max' => 'Описание не должно превышать 255 символов.',

            'starts_at.date_format' => 'Указан неверный формат даты начала. Ожидается ISO 8601 (например, 2026-11-05T10:00:00+03:00).',
            'starts_at.after_or_equal' => 'Дата начала резервирования не может быть в прошлом.',

            'ends_at.date_format' => 'Указан неверный формат даты окончания. Ожидается ISO 8601 (например, 2026-11-05T11:00:00+03:00).',
        ];
    }
}
