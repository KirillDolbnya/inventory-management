<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateWarehouseLayoutRequest extends FormRequest
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
            'model_path' => ['sometimes', 'file', 'extensions:glb', 'max:2048'],
            'racks' => ['sometimes', 'list'],
            'racks.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'racks.*.position_x' => ['required', 'numeric'],
            'racks.*.position_y' => ['required', 'numeric'],
            'racks.*.position_z' => ['required', 'numeric'],
            'racks.*.rotation_y_deg' => ['required', 'numeric', 'between:-360,360'],
            'racks.*.width' => ['required', 'numeric', 'gt:0'],
            'racks.*.depth' => ['required', 'numeric', 'gt:0'],
            'racks.*.height' => ['required', 'numeric', 'gt:0'],
            'walls' => ['sometimes', 'array'],
            'walls.outline' => [Rule::requiredIf(fn () => $this->has('walls')), 'list', 'min:3', 'max:10'],
            'walls.outline.*' => ['required', 'array'],
            'walls.outline.*.x' => ['required', 'numeric', 'min:-10000', 'max:10000'],
            'walls.outline.*.z' => ['required', 'numeric', 'min:-10000', 'max:10000'],
        ];
    }

    /**
     * @return array{callable}
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->any()) {
                    return;
                }

                if (! $this->has('walls.outline')) {
                    return;
                }

                $value = $this->input('walls.outline');

                $points = array_values($value);
                $count = count($points);

                for ($i = 0; $i < $count; $i++) {
                    $current = $points[$i];
                    $next = $points[($i + 1) % $count];

                    if (abs($current['x'] - $next['x']) < 0.0001 && abs($current['z'] - $next['z']) < 0.0001) {
                        $validator->errors()->add('walls.outline', "Обнаружена стена нулевой длины. Точка #{$i} совпадает со следующей.");

                        return;
                    }
                }

                $area = 0.0;
                for ($i = 0; $i < $count; $i++) {
                    $j = ($i + 1) % $count;
                    $area += $points[$i]['x'] * $points[$j]['z'];
                    $area -= $points[$j]['x'] * $points[$i]['z'];
                }
                $area = abs($area) / 2.0;

                if ($area < 0.01) {
                    $validator->errors()->add('walls.outline', 'Помещение имеет нулевую площадь. Все точки контура не могут лежать на одной прямой.');

                    return;
                }

                for ($i = 0; $i < $count; $i++) {
                    for ($k = $i + 2; $k < $count; $k++) {
                        if ($i === 0 && $k === $count - 1) {
                            continue;
                        }

                        $p1 = $points[$i];
                        $p2 = $points[($i + 1) % $count];
                        $p3 = $points[$k];
                        $p4 = $points[($k + 1) % $count];

                        if ($this->hasIntersection($p1, $p2, $p3, $p4)) {
                            $validator->errors()->add('walls.outline', "Стены помещения пересекают друг друга между отрезками ({$i}—".(($i + 1) % $count).") и ({$k}—".(($k + 1) % $count).').');

                            return;
                        }
                    }
                }
            },
        ];
    }

    /**
     * @param  array{x: float|int, z: float|int}  $p1
     * @param  array{x: float|int, z: float|int}  $p2
     * @param  array{x: float|int, z: float|int}  $p3
     * @param  array{x: float|int, z: float|int}  $p4
     */
    private function hasIntersection(array $p1, array $p2, array $p3, array $p4): bool
    {
        $crossProduct = fn ($ax, $az, $bx, $bz, $cx, $cz) => ($bx - $ax) * ($cz - $az) - ($bz - $az) * ($cx - $ax);

        $v1 = $crossProduct($p3['x'], $p3['z'], $p4['x'], $p4['z'], $p1['x'], $p1['z']);
        $v2 = $crossProduct($p3['x'], $p3['z'], $p4['x'], $p4['z'], $p2['x'], $p2['z']);
        $v3 = $crossProduct($p1['x'], $p1['z'], $p2['x'], $p2['z'], $p3['x'], $p3['z']);
        $v4 = $crossProduct($p1['x'], $p1['z'], $p2['x'], $p2['z'], $p4['x'], $p4['z']);

        if ((($v1 > 0 && $v2 < 0) || ($v1 < 0 && $v2 > 0)) && (($v3 > 0 && $v4 < 0) || ($v3 < 0 && $v4 > 0))) {
            return true;
        }

        $onSegment = fn ($ax, $az, $bx, $bz, $kx, $kz) => $kx >= min($ax, $bx) && $kx <= max($ax, $bx) &&
            $kz >= min($az, $bz) && $kz <= max($az, $bz);

        $epsilon = 0.000001;

        if (abs($v1) < $epsilon && $onSegment($p3['x'], $p3['z'], $p4['x'], $p4['z'], $p1['x'], $p1['z'])) {
            return true;
        }
        if (abs($v2) < $epsilon && $onSegment($p3['x'], $p3['z'], $p4['x'], $p4['z'], $p2['x'], $p2['z'])) {
            return true;
        }
        if (abs($v3) < $epsilon && $onSegment($p1['x'], $p1['z'], $p2['x'], $p2['z'], $p3['x'], $p3['z'])) {
            return true;
        }
        if (abs($v4) < $epsilon && $onSegment($p1['x'], $p1['z'], $p2['x'], $p2['z'], $p4['x'], $p4['z'])) {
            return true;
        }

        return false;
    }

    public function messages(): array
    {
        return [
            'model_path.file' => 'Загружаемый объект должен быть валидным файлом.',
            'model_path.extensions' => 'Файл модели должен иметь расширение .glb.',
            'model_path.max' => 'Размер файла 3D-модели не должен превышать 2 МБ (2048 КБ).',

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

            'walls.array' => 'Данные стен должны быть переданы в виде объекта (ассоциативного массива).',

            'walls.outline.required' => 'Контур стен помещения обязателен для заполнения.',
            'walls.outline.array' => 'Контур помещения должен быть последовательным списком точек.',
            'walls.outline.min' => 'Контур помещения должен содержать как минимум 3 точки для построения 3D-геометрии.',
            'walls.outline.max' => 'Контур помещения не может содержать как больше 10 точек.',

            'walls.outline.*.required' => 'Каждая точка контура обязательна для заполнения и не может быть пустой.',
            'walls.outline.*.array' => 'Каждая точка контура должна быть передана в виде массива координат {x, z}.',

            'walls.outline.*.x.required' => 'Координата X является обязательной для каждой точки контура.',
            'walls.outline.*.x.numeric' => 'Координата X должна быть числом (целым или с плавающей точкой).',
            'walls.outline.*.x.min' => 'Координата X не может быть меньше :min.',
            'walls.outline.*.x.max' => 'Координата X не может быть больше :max.',

            'walls.outline.*.z.required' => 'Координата Z является обязательной для каждой точки контура.',
            'walls.outline.*.z.numeric' => 'Координата Z должна быть числом (целым или с плавающей точкой).',
            'walls.outline.*.z.min' => 'Координата Z не может быть меньше :min.',
            'walls.outline.*.z.max' => 'Координата Z не может быть больше :max.',
        ];
    }
}
