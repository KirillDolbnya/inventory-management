<?php

use App\Exceptions\WarehouseModelUploadFailedException;
use App\Models\Cell;
use App\Models\InventoryPlacement;
use App\Models\Rack;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;

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

describe('Delete layout', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->warehouse = Warehouse::factory()->create();

        $this->rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $this->cell = Cell::factory()->create([
            'rack_id' => $this->rack->id,
        ]);

        $this->rackLayout = [
            'position_x' => '10.5',
            'position_y' => '0',
            'position_z' => '-4.2',
            'rotation_y_deg' => '90',
            'width' => '4.05',
            'depth' => '4.05',
            'height' => '10',
        ];

        $this->rackData = array_merge($this->rack->toArray(), $this->rackLayout);

        $this->warehouseWalls = [
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

    it('deletes the layout of an unoccupied warehouse', function () {
        $storage = Storage::fake('public');
        $fakeFile = UploadedFile::fake()->create('warehouse_map.glb', 500);
        $savedPath = $fakeFile->store('models', 'public');

        $this->warehouse->update(array_merge($this->warehouseWalls, [
            'model_path' => $savedPath,
        ]));

        $this->rack->upsert(
            [array_merge(['id' => $this->rack->id], $this->rackData)],
            ['id'],
            ['position_x', 'position_y', 'position_z', 'rotation_y_deg', 'width', 'depth', 'height']
        );

        $response = $this->be($this->user)->deleteJson(route('warehouses-delete-layout', $this->warehouse->id));

        $response->assertStatus(204);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'walls' => null,
            'model_path' => null,
            'deleted_at' => null,
        ]);

        Storage::disk('public')->assertMissing($savedPath);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'position_x' => null,
            'position_y' => null,
            'position_z' => null,
            'rotation_y_deg' => null,
            'width' => null,
            'depth' => null,
            'height' => null,
        ]);

        assertDatabaseHas('cells', [
            'id' => $this->cell->id,
            'rack_id' => $this->rack->id,
        ]);
    });

    it('returns 204 when no layout has been saved', function () {
        $response = $this->be($this->user)->deleteJson(route('warehouses-delete-layout', $this->warehouse->id));

        $response->assertStatus(204);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'walls' => null,
            'model_path' => null,
            'deleted_at' => null,
        ]);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'position_x' => null,
            'position_y' => null,
            'position_z' => null,
            'rotation_y_deg' => null,
            'width' => null,
            'depth' => null,
            'height' => null,
        ]);
    });

    it('rejects deleting the layout of a warehouse with inventory placements', function () {
        $storage = Storage::fake('public');
        $fakeFile = UploadedFile::fake()->create('warehouse_map.glb', 500);
        $savedPath = $fakeFile->store('models', 'public');

        $this->warehouse->update(array_merge($this->warehouseWalls, [
            'model_path' => $savedPath,
        ]));

        $this->rack->upsert(
            [array_merge(['id' => $this->rack->id], $this->rackData)],
            ['id'],
            ['position_x', 'position_y', 'position_z', 'rotation_y_deg', 'width', 'depth', 'height']
        );

        $placement = InventoryPlacement::factory()->create([
            'cell_id' => $this->cell->id,
        ]);

        $response = $this->be($this->user)->deleteJson(route('warehouses-delete-layout', $this->warehouse->id));

        $response->assertStatus(409);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'walls' => json_encode(['outline' => $this->warehouseWalls['walls']['outline']]),
            'model_path' => $savedPath,
            'deleted_at' => null,
        ]);

        Storage::disk('public')->assertExists($savedPath);

        assertDatabaseHas('racks', array_merge(
            ['id' => $this->rack->id],
            $this->rackLayout
        ));

        assertDatabaseHas('inventory_placements', [
            'id' => $placement->id,
            'inventory_id' => $placement->inventory_id,
            'cell_id' => $this->cell->id,
            'quantity' => $placement->quantity,
        ]);
    });

    it('returns 404 when deleting a layout for a nonexistent warehouse', function () {
        $response = $this->be($this->user)->deleteJson(route('warehouses-delete-layout', $this->warehouse->id + 1000));

        $response->assertStatus(404);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'walls' => null,
            'model_path' => null,
            'deleted_at' => null,
        ]);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'position_x' => null,
            'position_y' => null,
            'position_z' => null,
            'rotation_y_deg' => null,
            'width' => null,
            'depth' => null,
            'height' => null,
        ]);
    });

    it('rejects deleting a layout without authentication', function () {
        $response = $this->deleteJson(route('warehouses-delete-layout', $this->warehouse->id));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});

describe('Update layout', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->storage = Storage::fake('public');
        $fakeFile = UploadedFile::fake()->create('warehouse_map.glb', 500);
        $this->savedPath = $fakeFile->store('models', 'public');

        $this->warehouse = Warehouse::factory()->create([
            'model_path' => $this->savedPath,
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
        ]);

        $this->rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'position_x' => '10.5',
            'position_y' => '0',
            'position_z' => '-4.2',
            'rotation_y_deg' => '90',
            'width' => '4.05',
            'depth' => '4.05',
            'height' => '10',
        ]);
    });

    it('updates the model file, wall outline, and rack geometry in one request', function () {
        $otherFakeFile = UploadedFile::fake()->create('warehouse_map2.glb', 500);

        $walls = [
            'outline' => [
                ['x' => 1, 'z' => 0],
                ['x' => 10, 'z' => 0],
                ['x' => 10, 'z' => 4],
                ['x' => 4, 'z' => 4],
                ['x' => 4, 'z' => 10],
                ['x' => 1, 'z' => 10],
            ],
        ];

        $racks = [
            'id' => $this->rack->id,
            'position_x' => '11.5',
            'position_y' => '0',
            'position_z' => '-3.2',
            'rotation_y_deg' => '80',
            'width' => '4',
            'depth' => '4.1',
            'height' => '12',
        ];

        $response = $this->be($this->user)->patchJson(route('warehouses-update-layout', $this->warehouse->id), [
            'model_path' => $otherFakeFile,
            'racks' => [
                $racks,
            ],
            'walls' => $walls,
        ]);

        $response->assertStatus(200);

        $this->warehouse->refresh();
        $this->rack->refresh();

        $response->assertJson([
            'data' => [
                'id' => $this->warehouse->id,
                'walls' => $walls,
                'model' => [
                    'url' => $this->storage->url($this->warehouse->model_path),
                    'format' => $this->warehouse::FORMAT,
                    'unit' => $this->warehouse::UNIT,
                    'origin' => $this->warehouse::ORIGIN,
                ],
                'racks' => [
                    [
                        'id' => $this->rack->id,
                        'code' => $this->rack->code,
                        'position' => [
                            'x' => $racks['position_x'],
                            'y' => $racks['position_y'],
                            'z' => $racks['position_z'],
                        ],
                        'rotation_y_deg' => $racks['rotation_y_deg'],
                        'dimensions' => [
                            'width' => $racks['width'],
                            'height' => $racks['height'],
                            'depth' => $racks['depth'],
                        ],
                        'levels_count' => $this->rack->levels_count,
                        'cells_per_level' => $this->rack->cells_per_level,
                    ],
                ],
            ],
        ]);

        $this->storage->assertExists($this->warehouse->model_path);
        $this->storage->assertMissing($this->savedPath);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'model_path' => $this->warehouse->model_path,
            'walls' => json_encode($walls),
        ]);

        assertDatabaseHas('racks', $racks);
    });

    it('replaces the model file and preserves the walls and rack geometry', function () {
        $walls = [
            'outline' => [
                ['x' => 0, 'z' => 0],
                ['x' => 10, 'z' => 0],
                ['x' => 10, 'z' => 4],
                ['x' => 4, 'z' => 4],
                ['x' => 4, 'z' => 10],
                ['x' => 0, 'z' => 10],
            ],
        ];

        $otherFakeFile = UploadedFile::fake()->create('warehouse_map2.glb', 500);

        $response = $this->be($this->user)->patchJson(route('warehouses-update-layout', $this->warehouse->id), [
            'model_path' => $otherFakeFile,
        ]);

        $response->assertStatus(200);

        $this->warehouse->refresh();

        $this->storage->assertExists($this->warehouse->model_path);
        $this->storage->assertMissing($this->savedPath);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'model_path' => $this->warehouse->model_path,
            'walls' => json_encode($walls),
        ]);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'position_x' => $this->rack->position_x,
            'position_y' => $this->rack->position_y,
            'position_z' => $this->rack->position_z,
            'rotation_y_deg' => $this->rack->rotation_y_deg,
            'width' => $this->rack->width,
            'depth' => $this->rack->depth,
            'height' => $this->rack->height,
        ]);
    });

    it('updates the wall outline and preserves the model file and rack geometry', function () {
        $walls = [
            'outline' => [
                ['x' => 1, 'z' => 0],
                ['x' => 10, 'z' => 0],
                ['x' => 10, 'z' => 4],
                ['x' => 4, 'z' => 4],
                ['x' => 4, 'z' => 10],
                ['x' => 1, 'z' => 10],
            ],
        ];

        $response = $this->be($this->user)->patchJson(route('warehouses-update-layout', $this->warehouse->id), [
            'walls' => $walls,
        ]);

        $response->assertStatus(200);

        $this->warehouse->refresh();

        expect($this->warehouse->model_path)->toBe($this->savedPath);
        $this->storage->assertExists($this->warehouse->model_path);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'walls' => json_encode($walls),
        ]);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'position_x' => $this->rack->position_x,
            'position_y' => $this->rack->position_y,
            'position_z' => $this->rack->position_z,
            'rotation_y_deg' => $this->rack->rotation_y_deg,
            'width' => $this->rack->width,
            'depth' => $this->rack->depth,
            'height' => $this->rack->height,
        ]);
    });

    it('updates the selected rack geometry and preserves the other rack', function () {
        $otherRack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'position_x' => '9.5',
            'position_y' => '1',
            'position_z' => '-2.2',
            'rotation_y_deg' => '70',
            'width' => '5.1',
            'depth' => '5',
            'height' => '10',
        ]);

        $otherRackData = [
            'id' => $otherRack->id,
            'position_x' => '9.5',
            'position_y' => '1',
            'position_z' => '-2.2',
            'rotation_y_deg' => '70',
            'width' => '5.1',
            'depth' => '5',
            'height' => '10',
        ];

        $racks = [
            'id' => $this->rack->id,
            'position_x' => '11.5',
            'position_y' => '0',
            'position_z' => '-3.2',
            'rotation_y_deg' => '80',
            'width' => '4',
            'depth' => '4.1',
            'height' => '12',
        ];

        $response = $this->be($this->user)->patchJson(route('warehouses-update-layout', $this->warehouse->id), [
            'racks' => [
                $racks,
            ],
        ]);

        $response->assertStatus(200);

        $this->rack->refresh();

        $this->storage->assertExists($this->warehouse->model_path);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'model_path' => $this->warehouse->model_path,
            'walls' => json_encode(['outline' => $this->warehouse->walls['outline']]),
        ]);

        assertDatabaseHas('racks', $racks);
        assertDatabaseHas('racks', $otherRackData);
    });

    it('rejects an invalid layout update payload', function (array $payload, string $errorKey, string $extendMessage) {
        $response = $this->be($this->user)->patchJson(route('warehouses-update-layout', $this->warehouse->id), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => $extendMessage,
            ]);

        $this->storage->assertExists($this->warehouse->model_path);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'model_path' => $this->warehouse->model_path,
            'walls' => json_encode(['outline' => $this->warehouse->walls['outline']]),
        ]);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'position_x' => $this->rack->position_x,
            'position_y' => $this->rack->position_y,
            'position_z' => $this->rack->position_z,
            'rotation_y_deg' => $this->rack->rotation_y_deg,
            'width' => $this->rack->width,
            'depth' => $this->rack->depth,
            'height' => $this->rack->height,
        ]);
    })->with([
        'model_path: не файл' => [
            ['model_path' => 'just-string'],
            'model_path',
            'Загружаемый объект должен быть валидным файлом.',
        ],
        'model_path: неверное расширение' => [
            fn () => ['model_path' => UploadedFile::fake()->create('image.png', 500)],
            'model_path',
            'Файл модели должен иметь расширение .glb.',
        ],
        'model_path: превышен максимальный размер' => [
            fn () => ['model_path' => UploadedFile::fake()->create('map.glb', 2049)],
            'model_path',
            'Размер файла 3D-модели не должен превышать 2 МБ (2048 КБ).',
        ],
        'racks: не список' => [
            ['racks' => 'not-an-array'],
            'racks',
            'Список стеллажей должен быть последовательным массивом.',
        ],
        'racks: пропущен обязательный ID' => [
            ['racks' => [['position_x' => 10, 'position_y' => 0, 'position_z' => 5]]],
            'racks.0.id',
            'ID стеллажа обязателен.',
        ],
        'racks: ID не число' => [
            ['racks' => [['id' => 'string-id', 'position_x' => 1]]],
            'racks.0.id',
            'ID стеллажа должен быть целым числом.',
        ],
        'racks: ID меньше 1' => [
            ['racks' => [['id' => 0, 'position_x' => 1]]],
            'racks.0.id',
            'Неверный ID стеллажа.',
        ],
        'racks: дубликаты ID' => [
            ['racks' => [['id' => 5, 'position_x' => 1], ['id' => 5, 'position_x' => 2]]],
            'racks.0.id',
            'ID стеллажей не могут дублироваться',
        ],
        'racks: пропущена координата X' => [
            ['racks' => [['id' => 1, 'position_y' => 0, 'position_z' => 0]]],
            'racks.0.position_x',
            'Координата Position X обязательна.',
        ],
        'racks: координата X не число' => [
            ['racks' => [['id' => 1, 'position_x' => 'not-numeric']]],
            'racks.0.position_x',
            'Координата Position X должна быть числом.',
        ],
        'racks: угол поворота выходит за диапазон' => [
            ['racks' => [['id' => 1, 'position_x' => 1, 'position_y' => 0, 'position_z' => 0, 'rotation_y_deg' => 361]]],
            'racks.0.rotation_y_deg',
            'Угол поворота должен быть в диапазоне от -360 до 360 градусов.',
        ],
        'racks: ширина меньше или равна 0' => [
            ['racks' => [['id' => 1, 'position_x' => 1, 'position_y' => 0, 'position_z' => 0, 'rotation_y_deg' => 90, 'width' => 0]]],
            'racks.0.width',
            'Ширина стеллажа должна быть больше 0.',
        ],
        'walls: outline обязателен, если есть walls' => [
            ['walls' => []],
            'walls.outline',
            'Контур стен помещения обязателен для заполнения.',
        ],
        'walls: менее 3-х точек' => [
            ['walls' => ['outline' => [['x' => 0, 'z' => 0], ['x' => 10, 'z' => 0]]]],
            'walls.outline',
            'Контур помещения должен содержать как минимум 3 точки для построения 3D-геометрии.',
        ],
        'walls: пропущен параметр X в точке' => [
            ['walls' => ['outline' => [['z' => 0], ['x' => 10, 'z' => 0], ['x' => 10, 'z' => 10]]]],
            'walls.outline.0.x',
            'Координата X является обязательной для каждой точки контура.',
        ],
        'walls: стена нулевой длины' => [
            ['walls' => ['outline' => [['x' => 0, 'z' => 0], ['x' => 0, 'z' => 0], ['x' => 10, 'z' => 10]]]],
            'walls.outline',
            'Обнаружена стена нулевой длины. Точка #0 совпадает со следующей.',
        ],
        'walls: нулевая площадь' => [
            ['walls' => ['outline' => [['x' => 0, 'z' => 0], ['x' => 5, 'z' => 0], ['x' => 10, 'z' => 0]]]],
            'walls.outline',
            'Помещение имеет нулевую площадь. Все точки контура не могут лежать на одной прямой.',
        ],
        'walls: самопересечение стен' => [
            ['walls' => ['outline' => [
                ['x' => 0, 'z' => 0],
                ['x' => 10, 'z' => 0],
                ['x' => 10, 'z' => 10],
                ['x' => -5, 'z' => 5],
                ['x' => 5, 'z' => -5],
            ]]],
            'walls.outline',
            'Стены помещения пересекают друг друга между отрезками (0—1) и (3—4).',
        ],
    ]);

    it('returns 500 and keeps the existing model file when the upload fails', function () {
        Storage::shouldReceive('disk')
            ->with('public')
            ->andReturnSelf();

        Storage::shouldReceive('putFileAs')
            ->andReturn(false);

        Exceptions::fake();

        $otherFakeFile = UploadedFile::fake()->create('warehouse_map2.glb', 500);

        $response = $this->be($this->user)->patchJson(route('warehouses-update-layout', $this->warehouse->id), [
            'model_path' => $otherFakeFile,
        ]);

        $response->assertStatus(500);

        $response->assertJson([
            'message' => 'Не удалось сохранить файл, попробуйте еще раз.',
        ]);

        Exceptions::assertReported(WarehouseModelUploadFailedException::class);

        $notSavedPath = 'models/'.$otherFakeFile->hashName();

        $this->storage->assertMissing($notSavedPath);
        $this->storage->assertExists($this->warehouse->model_path);
    });

    it('returns 404 and removes the uploaded model file when a rack is missing', function () {
        $otherFakeFile = UploadedFile::fake()->create('warehouse_map2.glb', 500);

        $racks = [
            'id' => $this->rack->id + 1000,
            'position_x' => '10.5',
            'position_y' => '0',
            'position_z' => '-4.2',
            'rotation_y_deg' => '90',
            'width' => '4.05',
            'depth' => '4.05',
            'height' => '10',
        ];

        $response = $this->be($this->user)->patchJson(route('warehouses-update-layout', $this->warehouse->id), [
            'model_path' => $otherFakeFile,
            'racks' => [
                $racks,
            ],
        ]);

        $response->assertStatus(404);

        $notSavedPath = 'models/'.$otherFakeFile->hashName();

        $this->storage->assertMissing($notSavedPath);
        $this->storage->assertExists($this->warehouse->model_path);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'model_path' => $this->warehouse->model_path,
            'walls' => json_encode(['outline' => $this->warehouse->walls['outline']]),
        ]);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'position_x' => $this->rack->position_x,
            'position_y' => $this->rack->position_y,
            'position_z' => $this->rack->position_z,
            'rotation_y_deg' => $this->rack->rotation_y_deg,
            'width' => $this->rack->width,
            'depth' => $this->rack->depth,
            'height' => $this->rack->height,
        ]);
    });

    it('returns 404 when updating the layout of a nonexistent warehouse', function () {
        $otherFakeFile = UploadedFile::fake()->create('warehouse_map2.glb', 500);

        $walls = [
            'outline' => [
                ['x' => 1, 'z' => 0],
                ['x' => 10, 'z' => 0],
                ['x' => 10, 'z' => 4],
                ['x' => 4, 'z' => 4],
                ['x' => 4, 'z' => 10],
                ['x' => 1, 'z' => 10],
            ],
        ];

        $racks = [
            'id' => $this->rack->id,
            'position_x' => '10.5',
            'position_y' => '0',
            'position_z' => '-4.2',
            'rotation_y_deg' => '90',
            'width' => '4.05',
            'depth' => '4.05',
            'height' => '10',
        ];

        $response = $this->be($this->user)->patchJson(route('warehouses-update-layout', $this->warehouse->id + 1000), [
            'model_path' => $otherFakeFile,
            'racks' => [
                $racks,
            ],
            'walls' => $walls,
        ]);

        $response->assertStatus(404);

        $notSavedPath = 'models/'.$otherFakeFile->hashName();

        $this->storage->assertMissing($notSavedPath);
        $this->storage->assertExists($this->warehouse->model_path);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'model_path' => $this->warehouse->model_path,
            'walls' => json_encode(['outline' => $this->warehouse->walls['outline']]),
        ]);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'position_x' => $this->rack->position_x,
            'position_y' => $this->rack->position_y,
            'position_z' => $this->rack->position_z,
            'rotation_y_deg' => $this->rack->rotation_y_deg,
            'width' => $this->rack->width,
            'depth' => $this->rack->depth,
            'height' => $this->rack->height,
        ]);
    });

    it('rejects an unauthenticated layout update', function () {
        $otherFakeFile = UploadedFile::fake()->create('warehouse_map2.glb', 500);

        $walls = [
            'outline' => [
                ['x' => 1, 'z' => 0],
                ['x' => 10, 'z' => 0],
                ['x' => 10, 'z' => 4],
                ['x' => 4, 'z' => 4],
                ['x' => 4, 'z' => 10],
                ['x' => 1, 'z' => 10],
            ],
        ];

        $racks = [
            'id' => $this->rack->id,
            'position_x' => '10.5',
            'position_y' => '0',
            'position_z' => '-4.2',
            'rotation_y_deg' => '90',
            'width' => '4.05',
            'depth' => '4.05',
            'height' => '10',
        ];

        $response = $this->patchJson(route('warehouses-update-layout', $this->warehouse->id), [
            'model_path' => $otherFakeFile,
            'racks' => [
                $racks,
            ],
            'walls' => $walls,
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        $notSavedPath = 'models/'.$otherFakeFile->hashName();

        $this->storage->assertMissing($notSavedPath);
        $this->storage->assertExists($this->warehouse->model_path);

        assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'model_path' => $this->warehouse->model_path,
            'walls' => json_encode(['outline' => $this->warehouse->walls['outline']]),
        ]);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'position_x' => $this->rack->position_x,
            'position_y' => $this->rack->position_y,
            'position_z' => $this->rack->position_z,
            'rotation_y_deg' => $this->rack->rotation_y_deg,
            'width' => $this->rack->width,
            'depth' => $this->rack->depth,
            'height' => $this->rack->height,
        ]);
    });
});
