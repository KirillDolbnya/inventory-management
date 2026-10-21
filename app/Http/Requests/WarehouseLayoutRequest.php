<?php

namespace App\Http\Requests;

use Dedoc\Scramble\Attributes\BodyParameter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

#[BodyParameter('model_path', type: 'string', format: 'binary', description: 'Файл 3D-модели склада в формате GLB')]
#[BodyParameter('racks', type: 'array', description: 'Список стеллажей для размещения на 3D-карте')]
#[BodyParameter('racks.*.id', type: 'integer', description: 'Уникальный ID стеллажа в системе', example: 1)]
#[BodyParameter('racks.*.position_x', type: 'float', description: 'Координата по оси X в 3D-пространстве (в метрах)', example: 10.5)]
#[BodyParameter('racks.*.position_y', type: 'float', description: 'Координата по оси Y (высота от пола в метрах)', example: 0.0)]
#[BodyParameter('racks.*.position_z', type: 'float', description: 'Координата по оси Z в 3D-пространстве (в метрах)', example: -4.2)]
#[BodyParameter('racks.*.rotation_y_deg', type: 'float', description: 'Угол поворота вокруг вертикальной оси в градусах', example: 90)]
#[BodyParameter('racks.*.width', type: 'float', description: 'Физическая ширина стеллажа', example: 4.05)]
#[BodyParameter('racks.*.depth', type: 'float', description: 'Физическая глубина стеллажа', example: 4.05)]
#[BodyParameter('racks.*.height', type: 'float', description: 'Физическая высота стеллажа', example: 4.05)]
class WarehouseLayoutRequest extends FormRequest
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
            'model_path' => ['required', 'file', 'extensions:glb', 'max:2048'],
            'racks' => ['required', 'list'],
            'racks.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'racks.*.position_x' => ['required', 'numeric'],
            'racks.*.position_y' => ['required', 'numeric'],
            'racks.*.position_z' => ['required', 'numeric'],
            'racks.*.rotation_y_deg' => ['required', 'numeric', 'between:-360,360'],
            'racks.*.width' => ['required', 'numeric', 'gt:0'],
            'racks.*.depth' => ['required', 'numeric', 'gt:0'],
            'racks.*.height' => ['required', 'numeric', 'gt:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'model_path.required' => 'Файл 3D-модели обязателен для загрузки.',
            'model_path.file' => 'Загружаемый объект должен быть валидным файлом.',
            'model_path.extensions' => 'Файл модели должен иметь расширение .glb.',
            'model_path.max' => 'Размер файла 3D-модели не должен превышать 2 МБ (2048 КБ).',

            'racks.required' => 'Список стеллажей обязателен для заполнения.',
            'racks.list' => 'Список стеллажей должен быть последовательным массивом.',

            'racks.*.id.required' => 'ID стеллажа обязателен.',
            'racks.*.id.integer' => 'ID стеллажа должен быть целым числом.',
            'racks.*.id.min' => 'Неверный ID стеллажа.',
            'racks.*.id.distinct' => 'ID стеллажей не могут дублироваться',

            'racks.*.position_x.required' => 'Координата Position X обязательна.',
            'racks.*.position_x.numeric' => 'Координата Position X должна быть числом.',

            'racks.*.position_y.required' => 'Координата Position Y обязательна.',
            'racks.*.position_y.numeric' => 'Координата Position Y должна быть числом.',

            'racks.*.position_z.required' => 'Координата Position Z обязательна.',
            'racks.*.position_z.numeric' => 'Координата Position Z должна быть числом.',

            'racks.*.rotation_y_deg.required' => 'Угол поворота Rotation Y обязателен.',
            'racks.*.rotation_y_deg.numeric' => 'Угол поворота Rotation Y должен быть числом.',
            'racks.*.rotation_y_deg.between' => 'Угол поворота должен быть в диапазоне от -360 до 360 градусов.',

            'racks.*.width.required' => 'Ширина стеллажа обязательна.',
            'racks.*.width.numeric' => 'Ширина стеллажа должна числом.',
            'racks.*.width.gt' => 'Ширина стеллажа должна быть больше 0.',

            'racks.*.depth.required' => 'Глубина стеллажа обязательна.',
            'racks.*.depth.numeric' => 'Глубина стеллажа должна числом.',
            'racks.*.depth.gt' => 'Глубина стеллажа должна быть больше 0.',

            'racks.*.height.required' => 'Высота стеллажа обязательна.',
            'racks.*.height.numeric' => 'Высота стеллажа должна числом.',
            'racks.*.height.gt' => 'Высота стеллажа должна быть больше 0.',
        ];
    }
}
