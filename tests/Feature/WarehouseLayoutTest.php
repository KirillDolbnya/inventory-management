<?php

use App\Models\Cell;
use App\Models\Rack;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

describe('Warehouse layout', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->warehouse = Warehouse::factory()->create();
        $this->rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        $this->cell = Cell::factory()->create([
            'rack_id' => $this->rack->id,
        ]);

        $this->storage = Storage::fake('public');

        $this->fakeFile = UploadedFile::fake()->create('warehouse_map.glb', 500);

        $this->payload = [
            'model_path' => $this->fakeFile,
            'racks' => [
                [
                    'id' => $this->rack->id,
                    'position_x' => '10.5',
                    'position_y' => '0',
                    'position_z' => '-4.2',
                    'rotation_y_deg' => '90',
                    'width' => '4.05',
                    'depth' => '4.05',
                    'height' => '10',
                ],
            ],
            'walls' => [
                'outline' => [
                    ['x' => 0, 'z' => 0],
                    ['x' => 10, 'z' => 0],
                    ['x' => 10, 'z' => 4],
                    ['x' => 4, 'z' => 4],
                    ['x' => 4, 'z' => 10],
                    ['x' => 0, 'z' => 10],
                ],
            ],
        ];
    });

    it('saves a warehouse layout', function () {
        $response = $this->be($this->user)->putJson(route('warehouses-layout', $this->warehouse->id), $this->payload);

        $response->assertStatus(200);

        $this->warehouse->refresh();
        $this->rack->refresh();

        $response->assertJson([
            'data' => [
                'id' => $this->warehouse->id,
                'walls' => $this->payload['walls'],
                'model' => [
                    'format' => $this->warehouse::FORMAT,
                    'unit' => $this->warehouse::UNIT,
                    'origin' => $this->warehouse::ORIGIN,
                ],
                'racks' => [
                    [
                        'id' => $this->rack->id,
                        'code' => $this->rack->code,
                        'position' => [
                            'x' => $this->rack->position_x,
                            'y' => $this->rack->position_y,
                            'z' => $this->rack->position_z,
                        ],
                        'rotation_y_deg' => $this->rack->rotation_y_deg,
                        'dimensions' => [
                            'width' => $this->rack->width,
                            'height' => $this->rack->height,
                            'depth' => $this->rack->depth,
                        ],
                        'levels_count' => $this->rack->levels_count,
                        'cells_per_level' => $this->rack->cells_per_level,
                    ],
                ],
            ],
        ]);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'walls' => json_encode($this->payload['walls']),
        ]);

        $this->storage->assertExists($this->warehouse->model_path);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'warehouse_id' => $this->warehouse->id,
            'position_x' => $this->payload['racks'][0]['position_x'],
            'position_y' => $this->payload['racks'][0]['position_y'],
            'position_z' => $this->payload['racks'][0]['position_z'],
            'rotation_y_deg' => $this->payload['racks'][0]['rotation_y_deg'],
            'width' => $this->payload['racks'][0]['width'],
            'height' => $this->payload['racks'][0]['height'],
            'depth' => $this->payload['racks'][0]['depth'],
        ]);

        expect($this->warehouse->model_path)
            ->toStartWith('models/')
            ->toEndWith('.glb')
            ->and($this->storage->url($this->warehouse->model_path))->toBe($response->json('data.model.url'));
    });

    it('returns the saved warehouse layout', function () {
        $storagePath = $this->fakeFile->storeAs('models', $this->fakeFile->getClientOriginalName(), 'public');

        $this->warehouse->update([
            'model_path' => $storagePath,
            'walls' => $this->payload['walls'],
        ]);

        $this->rack->update([
            'position_x' => $this->payload['racks'][0]['position_x'],
            'position_y' => $this->payload['racks'][0]['position_y'],
            'position_z' => $this->payload['racks'][0]['position_z'],
            'rotation_y_deg' => $this->payload['racks'][0]['rotation_y_deg'],
            'width' => $this->payload['racks'][0]['width'],
            'depth' => $this->payload['racks'][0]['depth'],
            'height' => $this->payload['racks'][0]['height'],
        ]);

        $response = $this->be($this->user)->getJson(route('warehouses-get-layout', $this->warehouse->id));

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [
                'id' => $this->warehouse->id,
                'walls' => $this->payload['walls'],
                'model' => [
                    'format' => $this->warehouse::FORMAT,
                    'unit' => $this->warehouse::UNIT,
                    'origin' => $this->warehouse::ORIGIN,
                ],
                'racks' => [
                    [
                        'id' => $this->rack->id,
                        'code' => $this->rack->code,
                        'position' => [
                            'x' => $this->rack->position_x,
                            'y' => $this->rack->position_y,
                            'z' => $this->rack->position_z,
                        ],
                        'rotation_y_deg' => $this->rack->rotation_y_deg,
                        'dimensions' => [
                            'width' => $this->rack->width,
                            'height' => $this->rack->height,
                            'depth' => $this->rack->depth,
                        ],
                        'levels_count' => $this->rack->levels_count,
                        'cells_per_level' => $this->rack->cells_per_level,
                    ],
                ],
            ],
        ]);

        expect($this->storage->url($this->warehouse->model_path))->toBe($response->json('data.model.url'));
    });

    it('returns 404 when no layout has been saved', function () {
        $response = $this->be($this->user)->getJson(route('warehouses-get-layout', $this->warehouse->id));

        $response->assertStatus(404)
            ->assertJson([
                'message' => '3D-схема склада настроена не полностью.',
            ]);
    });

    it('rejects an invalid layout payload', function (string $errorKey, string $expectedMessage, array $payload) {
        $response = $this->be($this->user)->putJson(route('warehouses-layout', $this->warehouse->id), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => [$expectedMessage],
            ]);
    })->with([
        'model_path is required' => [
            'model_path',
            'Файл 3D-модели обязателен для загрузки.',
            fn () => array_merge($this->payload, ['model_path' => null]),
        ],
        'model_path extension must be glb' => [
            'model_path',
            'Файл модели должен иметь расширение .glb.',
            fn () => array_merge($this->payload, ['model_path' => UploadedFile::fake()->create('avatar.png', 100)]),
        ],
        'model_path maximum size is 2MB' => [
            'model_path',
            'Размер файла 3D-модели не должен превышать 2 МБ (2048 КБ).',
            fn () => array_merge($this->payload, ['model_path' => UploadedFile::fake()->create('huge.glb', 3000)]),
        ],
        'racks list is required' => [
            'racks',
            'Список стеллажей обязателен для заполнения.',
            fn () => array_merge($this->payload, ['racks' => null]),
        ],
        'racks ids must be distinct' => [
            'racks.0.id',
            'ID стеллажей не могут дублироваться',
            fn () => array_merge($this->payload, [
                'racks' => [
                    ['id' => $this->rack->id, 'position_x' => 0, 'position_y' => 0, 'position_z' => 0, 'rotation_y_deg' => 0, 'width' => 1, 'depth' => 1, 'height' => 1],
                    ['id' => $this->rack->id, 'position_x' => 5, 'position_y' => 0, 'position_z' => 5, 'rotation_y_deg' => 0, 'width' => 1, 'depth' => 1, 'height' => 1],
                ],
            ]),
        ],
        'rack rotation angle must be between -360 and 360' => [
            'racks.0.rotation_y_deg',
            'Угол поворота должен быть в диапазоне от -360 до 360 градусов.',
            fn () => array_merge($this->payload, [
                'racks' => [
                    array_merge($this->payload['racks'][0], ['rotation_y_deg' => 450]),
                ],
            ]),
        ],
        'rack height must be greater than 0' => [
            'racks.0.height',
            'Высота стеллажа должна быть больше 0.',
            fn () => array_merge($this->payload, [
                'racks' => [
                    array_merge($this->payload['racks'][0], ['height' => 0]),
                ],
            ]),
        ],
        'walls configuration is required' => [
            'walls',
            'Данные конфигурации стен обязательны для заполнения.',
            fn () => array_merge($this->payload, ['walls' => null]),
        ],
        'walls outline must have min 3 points' => [
            'walls.outline',
            'Контур помещения должен содержать как минимум 3 точки для построения 3D-геометрии.',
            fn () => array_merge($this->payload, [
                'walls' => [
                    'outline' => [
                        ['x' => 0, 'z' => 0],
                        ['x' => 5, 'z' => 5],
                    ],
                ],
            ]),
        ],
        'geometry error: wall with zero length' => [
            'walls.outline',
            'Обнаружена стена нулевой длины. Точка #1 совпадает со следующей.',
            fn () => array_merge($this->payload, [
                'walls' => [
                    'outline' => [
                        ['x' => 0, 'z' => 0],
                        ['x' => 5, 'z' => 5],
                        ['x' => 5, 'z' => 5],
                        ['x' => 0, 'z' => 5],
                    ],
                ],
            ]),
        ],
        'walls outline exceeds max 10 points (11 points)' => [
            'walls.outline',
            'Контур помещения не может содержать как больше 10 точек.',
            fn () => array_merge($this->payload, [
                'walls' => [
                    'outline' => [
                        ['x' => 0, 'z' => 0],
                        ['x' => 2, 'z' => 0],
                        ['x' => 4, 'z' => 0],
                        ['x' => 6, 'z' => 0],
                        ['x' => 8, 'z' => 0],
                        ['x' => 8, 'z' => 8],
                        ['x' => 6, 'z' => 8],
                        ['x' => 4, 'z' => 8],
                        ['x' => 2, 'z' => 8],
                        ['x' => 0, 'z' => 8],
                        ['x' => 0, 'z' => 4],
                    ],
                ],
            ]),
        ],
        'geometry error: zero area (points on the same line)' => [
            'walls.outline',
            'Помещение имеет нулевую площадь. Все точки контура не могут лежать на одной прямой.',
            fn () => array_merge($this->payload, [
                'walls' => [
                    'outline' => [
                        ['x' => 0, 'z' => 0],
                        ['x' => 2, 'z' => 2],
                        ['x' => 4, 'z' => 4],
                        ['x' => 6, 'z' => 6],
                    ],
                ],
            ]),
        ],
        'geometry error: self-intersecting walls (hourglass)' => [
            'walls.outline',
            'Стены помещения пересекают друг друга между отрезками (0—1) и (2—3).',
            fn () => array_merge($this->payload, [
                'walls' => [
                    'outline' => [
                        ['x' => 0.0, 'z' => 0.0],
                        ['x' => 10.0, 'z' => 10.0],
                        ['x' => 10.0, 'z' => 2.0],
                        ['x' => -2.0, 'z' => 8.0],
                    ],
                ],
            ]),
        ],
    ]);

    it('returns 404 when saving a layout for a nonexistent warehouse', function () {
        $response = $this->be($this->user)->putJson(route('warehouses-layout', $this->warehouse->id + 1000), $this->payload);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Склад с передаваемым id не найден',
            ]);
    });

    it('rejects saving a layout without authentication', function () {
        $response = $this->putJson(route('warehouses-layout', $this->warehouse->id + 1000), $this->payload);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});
