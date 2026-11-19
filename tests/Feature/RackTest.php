<?php

use App\Models\Cell;
use App\Models\Event;
use App\Models\EventInventoryReservation;
use App\Models\InventoryPlacement;
use App\Models\Rack;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\WarehouseRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertNotSoftDeleted;
use function Pest\Laravel\assertSoftDeleted;

uses(RefreshDatabase::class);

describe('Rack', function () {
    beforeEach(function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        $this->user = User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $warehouseRepository = new WarehouseRepository;

        $this->warehouse = $warehouseRepository->create('Склад 1', 'г. Москва, Ленинские горы, д. 1', '8ed1481e-1f9e-4340-9774-325db197bf5d', 55.702936, 37.530768);
        $this->warehouseTwo = $warehouseRepository->create('Склад 2', 'г. Москва, ул. Петровка, д. 17', 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e', 55.7646600, 37.6162100);

        $this->racksData = [
            [
                'code' => 'A',
                'levels_count' => 2,
                'cells_per_level' => 3,
            ],
            [
                'code' => 'B',
                'levels_count' => 1,
                'cells_per_level' => 2,
            ],
        ];
    });

    it('creates racks with generated cells', function () {

        $response = $this->be($this->user)->postJson(route('racks-create', ['warehouseId' => $this->warehouse->id]), [
            'racks' => $this->racksData,
        ]);

        $response->assertStatus(201);

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'code',
                    'levels_count',
                    'cells_per_level',
                    'cells' => [
                        '*' => [
                            'id',
                            'number',
                            'level',
                            'position',
                        ],
                    ],
                ],
            ],
        ]);

        $responseRacks = collect($response->json('data'))->keyBy('code');

        $expectedRacks = collect($this->racksData)->keyBy('code');

        $response->assertJsonCount(count($this->racksData), 'data');

        foreach ($expectedRacks as $code => $expectedRack) {
            $actualRack = $responseRacks->get($code);

            $expectedCellsCount =
                $expectedRack['levels_count']
                * $expectedRack['cells_per_level'];

            expect($actualRack)->not->toBeNull()
                ->and($actualRack['levels_count'])->toBe($expectedRack['levels_count'])
                ->and($actualRack['cells_per_level'])->toBe($expectedRack['cells_per_level'])
                ->and($actualRack['cells'])->toHaveCount($expectedCellsCount);

            $actualCells = $actualRack['cells'];

            $cellNumbers = array_map(function ($actualCell) {
                return $actualCell['number'];
            }, $actualCells);

            expect($cellNumbers)->toBe(range(1, $expectedCellsCount));

            foreach ($actualCells as $actualCell) {
                $expectedLevel = intdiv($actualCell['number'] - 1, $expectedRack['cells_per_level']) + 1;
                $expectedPosition = (($actualCell['number'] - 1) % $expectedRack['cells_per_level']) + 1;

                expect($actualCell['level'])->toBe($expectedLevel)
                    ->and($actualCell['position'])->toBe($expectedPosition);
            }
        }

        $persistedRacks = Rack::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->withCount('cells')
            ->get()
            ->keyBy('code');

        foreach ($expectedRacks as $code => $expectedRack) {
            $actualRack = $persistedRacks->get($code);

            $expectedCellsCount =
                $expectedRack['levels_count']
                * $expectedRack['cells_per_level'];

            expect($actualRack)->not->toBeNull()
                ->and($actualRack->warehouse_id)->toBe($this->warehouse->id)
                ->and($actualRack->levels_count)->toBe($expectedRack['levels_count'])
                ->and($actualRack->cells_per_level)->toBe($expectedRack['cells_per_level'])
                ->and($actualRack->cells_count)->toBe($expectedCellsCount);
        }

        $racksCount = count($this->racksData);
        $cellsCount = collect($this->racksData)
            ->sum(fn (array $rackData) => $rackData['levels_count'] * $rackData['cells_per_level']);

        assertDatabaseCount('racks', $racksCount);
        assertDatabaseCount('cells', $cellsCount);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->postJson(route('racks-create', ['warehouseId' => $this->warehouse->id]), [
            'racks' => $this->racksData,
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });

    it('rejects a code used by another rack', function () {
        Rack::create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'A',
            'levels_count' => 1,
            'cells_per_level' => 2,
        ]);

        $response = $this->be($this->user)->postJson(route('racks-create', ['warehouseId' => $this->warehouse->id]), [
            'racks' => $this->racksData,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'Один или несколько кодов стеллажей уже заняты на этом складе.',
                'errors' => [
                    'code' => [
                        'A',
                    ],
                ],
            ]);

        $this->assertDatabaseCount('racks', 1);
        $this->assertDatabaseCount('cells', 0);
    });

    it('allows the same rack codes in different warehouses', function () {
        $response = $this->be($this->user)->postJson(route('racks-create', ['warehouseId' => $this->warehouse->id]), [
            'racks' => $this->racksData,
        ]);

        $response->assertStatus(201);

        $responseTwo = $this->be($this->user)->postJson(route('racks-create', ['warehouseId' => $this->warehouseTwo->id]), [
            'racks' => $this->racksData,
        ]);

        $responseTwo->assertStatus(201);
    });

    it('returns 404 when the warehouse does not exist', function () {
        $response = $this->be($this->user)->postJson(route('racks-create', ['warehouseId' => $this->warehouse->id + 1000]), [
            'racks' => $this->racksData,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Склад с передаваемым id не найден',
            ]);

        assertDatabaseCount('racks', 0);
        assertDatabaseCount('cells', 0);
    });

    it('returns 404 when the warehouse ID is not numeric', function () {
        $response = $this->be($this->user)->postJson(route('racks-create', ['warehouseId' => 'asd']), [
            'racks' => $this->racksData,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Запрашиваемый ресурс или маршрут не найден.',
            ]);

        assertDatabaseCount('racks', 0);
        assertDatabaseCount('cells', 0);
    });

    it('validates request fields and returns correct messages', function (array $payload, string $errorKey, string $expectedMessage) {
        $response = $this->be($this->user)->postJson(route('racks-create', ['warehouseId' => $this->warehouse->id]), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => $expectedMessage,
            ]);

        assertDatabaseCount('racks', 0);
        assertDatabaseCount('cells', 0);
    })->with([
        'racks required (empty payload)' => [
            [],
            'racks',
            'Массив стеллажей обязателен для заполнения.',
        ],
        'racks required (null value)' => [
            ['racks' => null],
            'racks',
            'Массив стеллажей обязателен для заполнения.',
        ],
        'racks string instead of array' => [
            ['racks' => 'not-an-array'],
            'racks',
            'Переданные данные стеллажей должны быть массивом.',
        ],
        'racks assoc instead of list' => [
            ['racks' => ['first' => ['code' => 'A', 'levels_count' => 1, 'cells_per_level' => 1]]],
            'racks',
            'Стеллажи должны быть переданы списком.',
        ],
        'racks max 10' => [
            [
                'racks' => array_fill(0, 11, ['code' => 'A', 'levels_count' => 1, 'cells_per_level' => 1]),
            ],
            'racks',
            'Нельзя за один запрос создать более 10 стеллажей.',
        ],
        'racks.*.required (element is null)' => [
            ['racks' => [null]],
            'racks.0',
            'Данные стеллажа обязательны.',
        ],
        'racks.*.array (element is string)' => [
            ['racks' => ['invalid-string-instead-of-array']],
            'racks.0',
            'Каждый элемент в массиве racks должен быть объектом с ключами: code, levels_count, cells_per_level.',
        ],
        'rack item structure invalid keys' => [
            ['racks' => [['wrong_key' => 'value']]],
            'racks.0',
            'Каждый элемент в массиве racks должен быть объектом с ключами: code, levels_count, cells_per_level.',
        ],
        'rack code required' => [
            ['racks' => [['levels_count' => 3, 'cells_per_level' => 4]]],
            'racks.0.code',
            'Пожалуйста, укажите код стеллажа.',
        ],
        'rack code integer instead of string' => [
            ['racks' => [['code' => 12345, 'levels_count' => 3, 'cells_per_level' => 4]]],
            'racks.0.code',
            'Код стеллажа должен быть строкой.',
        ],
        'rack code max 20' => [
            ['racks' => [['code' => str_repeat('A', 21), 'levels_count' => 3, 'cells_per_level' => 4]]],
            'racks.0.code',
            'Код стеллажа не должен превышать 20 символов.',
        ],
        'rack code distinct' => [
            ['racks' => [['code' => 'A', 'levels_count' => 1, 'cells_per_level' => 1], ['code' => 'A', 'levels_count' => 1, 'cells_per_level' => 1]]],
            'racks.1.code',
            'Коды стеллажей в одном запросе не должны повторяться.',
        ],
        'rack code uppercase' => [
            ['racks' => [['code' => 'lower', 'levels_count' => 3, 'cells_per_level' => 4]]],
            'racks.0.code',
            'Код стеллажа должен быть написан заглавными буквами.',
        ],
        'levels_count required' => [
            ['racks' => [['code' => 'A', 'cells_per_level' => 4]]],
            'racks.0.levels_count',
            'Укажите количество уровней для стеллажа.',
        ],
        'levels_count integer (string given)' => [
            ['racks' => [['code' => 'A', 'levels_count' => 'three', 'cells_per_level' => 4]]],
            'racks.0.levels_count',
            'Количество уровней должно быть целым числом.',
        ],
        'levels_count min 1' => [
            ['racks' => [['code' => 'A', 'levels_count' => 0, 'cells_per_level' => 4]]],
            'racks.0.levels_count',
            'Количество уровней должно быть не менее 1.',
        ],
        'levels_count max 10' => [
            ['racks' => [['code' => 'A', 'levels_count' => 11, 'cells_per_level' => 4]]],
            'racks.0.levels_count',
            'Количество уровней не должно превышать 10.',
        ],
        'cells_per_level required' => [
            ['racks' => [['code' => 'A', 'levels_count' => 3]]],
            'racks.0.cells_per_level',
            'Укажите количество ячеек на уровень.',
        ],
        'cells_per_level integer (float given)' => [
            ['racks' => [['code' => 'A', 'levels_count' => 3, 'cells_per_level' => 4.5]]],
            'racks.0.cells_per_level',
            'Количество ячеек должно быть целым числом.',
        ],
        'cells_per_level min 1' => [
            ['racks' => [['code' => 'A', 'levels_count' => 3, 'cells_per_level' => 0]]],
            'racks.0.cells_per_level',
            'Количество ячеек на уровень должно быть не менее 1.',
        ],
        'cells_per_level max 10' => [
            ['racks' => [['code' => 'A', 'levels_count' => 3, 'cells_per_level' => 11]]],
            'racks.0.cells_per_level',
            'Количество ячеек на уровень не должно превышать 10.',
        ],
    ]);
});

describe('Rack index', function () {
    beforeEach(function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        $this->user = User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $warehouseRepository = new WarehouseRepository;

        $this->warehouse = $warehouseRepository->create('Склад 1', 'г. Москва, Ленинские горы, д. 1', '8ed1481e-1f9e-4340-9774-325db197bf5d', 55.702936, 37.530768);
        $this->warehouseTwo = $warehouseRepository->create('Склад 2', 'г. Москва, ул. Петровка, д. 17', 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e', 55.7646600, 37.6162100);

        Rack::create([
            'warehouse_id' => $this->warehouseTwo->id,
            'code' => 'C',
            'levels_count' => 1,
            'cells_per_level' => 2,
        ]);

        $this->racksData = [
            [
                'code' => 'A',
                'levels_count' => 2,
                'cells_per_level' => 3,
            ],
            [
                'code' => 'B',
                'levels_count' => 1,
                'cells_per_level' => 2,
            ],
        ];
    });

    it('returns a list of racks and cells', function () {
        $this->be($this->user)->postJson(route('racks-create', ['warehouseId' => $this->warehouse->id]), [
            'racks' => $this->racksData,
        ])->assertCreated();

        $response = $this->be($this->user)->getJson(route('racks-index', ['warehouseId' => $this->warehouse->id]));

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'code',
                    'levels_count',
                    'cells_per_level',
                    'cells' => [
                        '*' => [
                            'id',
                            'number',
                            'level',
                            'position',
                        ],
                    ],
                ],
            ],
        ]);

        $responseRacks = collect($response->json('data'))->keyBy('code');
        $expectedRacks = collect($this->racksData)->keyBy('code');

        expect($responseRacks->keys()->all())->toEqual($expectedRacks->keys()->all());

        $rackIds = collect($response->json('data'))->pluck('id')->all();
        $expectedRackIds = $rackIds;
        sort($expectedRackIds);

        expect($rackIds)->toEqual($expectedRackIds);

        foreach ($expectedRacks as $code => $expectedRack) {
            $actualRack = $responseRacks->get($code);

            $expectedCellsCount =
                $expectedRack['levels_count']
                * $expectedRack['cells_per_level'];

            expect($actualRack)->not->toBeNull()
                ->and($actualRack['levels_count'])->toBe($expectedRack['levels_count'])
                ->and($actualRack['cells_per_level'])->toBe($expectedRack['cells_per_level'])
                ->and($actualRack['cells'])->toHaveCount($expectedCellsCount);

            $cells = $actualRack['cells'];

            $cellNumbers = collect($cells)->pluck('number')->all();

            expect($cellNumbers)->toBe(range(1, $expectedCellsCount));
        }
    });

    it('returns an empty list when the warehouse has no racks', function () {
        $response = $this->be($this->user)->getJson(route('racks-index', ['warehouseId' => $this->warehouse->id]));

        $response->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->getJson(route('racks-index', ['warehouseId' => $this->warehouse->id]));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });

    it('returns 404 for a missing warehouse', function () {
        $response = $this->be($this->user)->getJson(route('racks-index', ['warehouseId' => $this->warehouse->id + 1000]));

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Склад с передаваемым id не найден',
            ]);
    });

    it('returns 404 when the warehouse ID is not numeric', function () {
        $response = $this->be($this->user)->getJson(route('racks-index', ['warehouseId' => 'asd']));

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Запрашиваемый ресурс или маршрут не найден.',
            ]);
    });
});

describe('Delete rack and cells', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->warehouse = Warehouse::factory()->create();

        $this->rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'levels_count' => 1,
            'cells_per_level' => 4,
        ]);

        $this->cells = Cell::factory()->count(3)
            ->sequence(fn ($sequence) => [
                'position' => $sequence->index + 1,
                'level' => 1,
                'number' => $sequence->index + 1,
            ])
            ->create([
                'rack_id' => $this->rack->id,
            ]);
    });

    it('archives a rack and all its cells', function () {
        $otherRack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $otherCell = Cell::factory()->create([
            'position' => 4,
            'rack_id' => $otherRack->id,
        ]);

        $this->travelTo(Carbon::parse('2026-12-05T10:00:00+03:00')->setTimezone('UTC'));
        $this->startsAt = Carbon::parse('2026-11-05T08:00:00+03:00');
        $this->endsAt = $this->startsAt->clone()->addHours(1);

        $event = Event::factory()->create([
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ]);

        $reservation = EventInventoryReservation::factory()->create([
            'event_id' => $event->id,
            'cell_id' => $this->cells->first()->id,
        ]);

        $response = $this->be($this->user)->deleteJson(route('racks-delete', [$this->warehouse->id, $this->rack->id]));

        $response->assertStatus(204);

        assertSoftDeleted('racks', [
            'id' => $this->rack->id,
        ]);

        assertNotSoftDeleted('racks', [
            'id' => $otherRack->id,
        ]);

        assertNotSoftDeleted('cells', [
            'id' => $otherCell->id,
        ]);

        assertDatabaseHas('event_inventory_reservations', [
            'id' => $reservation->id,
            'cell_id' => $this->cells->first()->id,
        ]);

        foreach ($this->cells as $cell) {
            assertSoftDeleted('cells', [
                'id' => $cell->id,
            ]);
        }

        $response = $this->be($this->user)->getJson(route('events-show', $event->id));

        $response->assertStatus(200);

        $reservationsData = collect($response->json('reservations'));
        $cellId = data_get($reservationsData->firstWhere('cell.id', $reservation->cell_id), 'cell.id');

        expect($cellId)->toBe($this->cells->first()->id);
    });

    it('rejects archiving a rack with inventory placements', function () {
        $placement = InventoryPlacement::factory()->create([
            'cell_id' => $this->cells->first()->id,
        ]);

        $response = $this->be($this->user)->deleteJson(route('racks-delete', [$this->warehouse->id, $this->rack->id]));

        $response->assertStatus(409);

        assertNotSoftDeleted('racks', [
            'id' => $this->rack->id,
        ]);

        foreach ($this->cells as $cell) {
            assertNotSoftDeleted('cells', [
                'id' => $cell->id,
            ]);
        }

        assertDatabaseHas('inventory_placements', [
            'id' => $placement->id,
        ]);
    });

    it('returns 404 when the rack belongs to another warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create();

        $response = $this->be($this->user)->deleteJson(route('racks-delete', [$otherWarehouse->id, $this->rack->id]));

        $response->assertStatus(404);

        assertNotSoftDeleted('racks', [
            'id' => $this->rack->id,
        ]);

        foreach ($this->cells as $cell) {
            assertNotSoftDeleted('cells', [
                'id' => $cell->id,
            ]);
        }
    });

    it('returns 404 when the warehouse does not exist', function () {
        $response = $this->be($this->user)->deleteJson(route('racks-delete', [$this->warehouse->id + 1000, $this->rack->id]));

        $response->assertStatus(404);

        assertNotSoftDeleted('racks', [
            'id' => $this->rack->id,
        ]);

        foreach ($this->cells as $cell) {
            assertNotSoftDeleted('cells', [
                'id' => $cell->id,
            ]);
        }
    });

    it('returns 404 when the rack does not exist', function () {
        $response = $this->be($this->user)->deleteJson(route('racks-delete', [$this->warehouse->id, $this->rack->id + 1000]));

        $response->assertStatus(404);

        assertNotSoftDeleted('racks', [
            'id' => $this->rack->id,
        ]);

        foreach ($this->cells as $cell) {
            assertNotSoftDeleted('cells', [
                'id' => $cell->id,
            ]);
        }
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->deleteJson(route('racks-delete', [$this->warehouse->id, $this->rack->id]));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        assertNotSoftDeleted('racks', [
            'id' => $this->rack->id,
        ]);

        foreach ($this->cells as $cell) {
            assertNotSoftDeleted('cells', [
                'id' => $cell->id,
            ]);
        }
    });
});

describe('Update rack and cell', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        $this->warehouse = Warehouse::factory()->create();
        $this->rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'levels_count' => 2,
            'cells_per_level' => 3,
        ]);

        $cellsPerLevel = 3;

        $this->cells = Cell::factory()->count(6)
            ->sequence(fn ($sequence) => [
                'number' => $sequence->index + 1,
                'level' => (int) ($sequence->index / $cellsPerLevel) + 1,
                'position' => ($sequence->index % $cellsPerLevel) + 1,
            ])
            ->create([
                'rack_id' => $this->rack->id,
            ]);
    });

    it('updates the code and expands the rack structure', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'code' => 'ST-01',
            'levels_count' => 3,
            'cells_per_level' => 4,
        ]);

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'id',
            'code',
            'levels_count',
            'cells_per_level',
            'cells' => [
                '*' => [
                    'id',
                    'number',
                    'level',
                    'position',
                ],
            ],
        ]);

        $response->assertJsonCount(12, 'cells');

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'code' => 'ST-01',
            'levels_count' => 3,
            'cells_per_level' => 4,
        ]);

        $expectedCells = $response->json('cells');
        $expectedCellsCount = $response->json('levels_count') * $response->json('cells_per_level');

        $expectedCellsNumber = collect($expectedCells)->map(function ($cell) {
            return $cell['number'];
        })->all();

        expect($expectedCellsNumber)->toBe(range(1, $expectedCellsCount));

        foreach ($expectedCells as $expectedCell) {
            expect($expectedCell['level'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['level'])->toBeLessThanOrEqual(3)
                ->and($expectedCell['position'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeLessThanOrEqual(4);

            assertDatabaseHas('cells', [
                'id' => $expectedCell['id'],
                'number' => $expectedCell['number'],
                'level' => $expectedCell['level'],
                'position' => $expectedCell['position'],
            ]);
        }
    });

    it('reduces the number of levels and cells per level', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'levels_count' => 1,
            'cells_per_level' => 2,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(2, 'cells');

        $expectedCells = $response->json('cells');
        $expectedCellsCount = $response->json('levels_count') * $response->json('cells_per_level');
        $expectedCellIds = collect($expectedCells)->pluck('id')->all();
        $deletedCells = $this->cells->whereNotIn('id', $expectedCellIds);

        $expectedCellsNumber = collect($expectedCells)->map(function ($cell) {
            return $cell['number'];
        })->all();

        expect($expectedCellsNumber)->toBe(range(1, $expectedCellsCount));

        foreach ($expectedCells as $expectedCell) {
            expect($expectedCell['level'])->toBe(1)
                ->and($expectedCell['position'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeLessThanOrEqual(2);

            assertDatabaseHas('cells', [
                'id' => $expectedCell['id'],
                'number' => $expectedCell['number'],
                'level' => $expectedCell['level'],
                'position' => $expectedCell['position'],
            ]);
        }

        foreach ($deletedCells as $deletedCell) {
            assertSoftDeleted('cells', [
                'id' => $deletedCell->id,
            ]);
        }
    });

    it('updates the rack code and preserves its cells', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'code' => 'ST-01',
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount($this->cells->count(), 'cells');

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'code' => 'ST-01',
        ]);

        foreach ($this->cells as $cell) {
            assertDatabaseHas('cells', [
                'id' => $cell->id,
                'number' => $cell->number,
                'level' => $cell->level,
                'position' => $cell->position,
                'deleted_at' => null,
            ]);
        }
    });

    it('reduces the number of levels and archives removed cells', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'code' => $this->rack->code,
            'levels_count' => 1,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(3, 'cells');

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'levels_count' => 1,
        ]);

        $expectedCells = $response->json('cells');
        $expectedCellIds = collect($expectedCells)->pluck('id')->all();
        $deletedCells = $this->cells->whereNotIn('id', $expectedCellIds);

        foreach ($expectedCells as $expectedCell) {
            expect($expectedCell['level'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeLessThanOrEqual(3);

            assertDatabaseHas('cells', [
                'id' => $expectedCell['id'],
                'number' => $expectedCell['number'],
                'level' => $expectedCell['level'],
                'position' => $expectedCell['position'],
            ]);
        }

        foreach ($deletedCells as $deletedCell) {
            assertSoftDeleted('cells', [
                'id' => $deletedCell->id,
                'number' => $deletedCell->number,
                'level' => $deletedCell->level,
                'position' => $deletedCell->position,
            ]);
        }
    });

    it('reduces cells per level and renumbers remaining cells', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'cells_per_level' => 2,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(4, 'cells');

        $expectedCells = $response->json('cells');
        $expectedCellsCount = $response->json('levels_count') * $response->json('cells_per_level');
        $expectedCellIds = collect($expectedCells)->pluck('id')->all();
        $deletedCells = $this->cells->whereNotIn('id', $expectedCellIds);

        $expectedCellsNumber = collect($expectedCells)->map(function ($cell) {
            return $cell['number'];
        })->all();

        expect($expectedCellsNumber)->toBe(range(1, $expectedCellsCount));

        foreach ($expectedCells as $expectedCell) {
            expect($expectedCell['level'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['level'])->toBeLessThanOrEqual(2)
                ->and($expectedCell['position'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeLessThanOrEqual(2);

            assertDatabaseHas('cells', [
                'id' => $expectedCell['id'],
                'number' => $expectedCell['number'],
                'level' => $expectedCell['level'],
                'position' => $expectedCell['position'],
            ]);
        }

        foreach ($deletedCells as $deletedCell) {
            assertSoftDeleted('cells', [
                'id' => $deletedCell->id,
                'number' => $deletedCell->number,
                'level' => $deletedCell->level,
                'position' => $deletedCell->position,
            ]);
        }
    });

    it('increases levels and reduces cells per level', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'levels_count' => 3,
            'cells_per_level' => 2,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(6, 'cells');

        $expectedCells = $response->json('cells');
        $expectedCellIds = collect($expectedCells)->pluck('id')->all();
        $deletedCells = $this->cells->whereNotIn('id', $expectedCellIds);

        foreach ($expectedCells as $expectedCell) {
            expect($expectedCell['level'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['level'])->toBeLessThanOrEqual(3)
                ->and($expectedCell['position'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeLessThanOrEqual(2);

            assertDatabaseHas('cells', [
                'id' => $expectedCell['id'],
                'number' => $expectedCell['number'],
                'level' => $expectedCell['level'],
                'position' => $expectedCell['position'],
            ]);
        }

        foreach ($deletedCells as $deletedCell) {
            assertSoftDeleted('cells', [
                'id' => $deletedCell->id,
                'number' => $deletedCell->number,
                'level' => $deletedCell->level,
                'position' => $deletedCell->position,
            ]);
        }
    });

    it('reduces levels and increases cells per level', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'levels_count' => 1,
            'cells_per_level' => 4,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(4, 'cells');

        $expectedCells = $response->json('cells');
        $expectedCellIds = collect($expectedCells)->pluck('id')->all();
        $deletedCells = $this->cells->whereNotIn('id', $expectedCellIds);

        foreach ($expectedCells as $expectedCell) {
            expect($expectedCell['level'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeLessThanOrEqual(4);

            assertDatabaseHas('cells', [
                'id' => $expectedCell['id'],
                'number' => $expectedCell['number'],
                'level' => $expectedCell['level'],
                'position' => $expectedCell['position'],
            ]);
        }

        foreach ($deletedCells as $deletedCell) {
            assertSoftDeleted('cells', [
                'id' => $deletedCell->id,
                'number' => $deletedCell->number,
                'level' => $deletedCell->level,
                'position' => $deletedCell->position,
            ]);
        }
    });

    it('rejects a code used by another rack in the warehouse', function () {
        $rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'code' => $rack->code,
        ]);

        $response->assertStatus(422);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'code' => $this->rack->code,
        ]);
    });

    it('increases cells per level and renumbers existing cells', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'cells_per_level' => 4,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(8, 'cells');

        $expectedCells = $response->json('cells');

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'cells_per_level' => 4,
        ]);

        foreach ($expectedCells as $expectedCell) {
            expect($expectedCell['level'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['level'])->toBeLessThanOrEqual(2)
                ->and($expectedCell['position'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeLessThanOrEqual(4);

            assertDatabaseHas('cells', [
                'id' => $expectedCell['id'],
                'number' => $expectedCell['number'],
                'level' => $expectedCell['level'],
                'position' => $expectedCell['position'],
            ]);
        }
    });

    it('allows shrinking when occupied cells are retained', function () {
        $placement = InventoryPlacement::factory()->create([
            'cell_id' => $this->cells->first()->id,
        ]);

        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'cells_per_level' => 1,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(2, 'cells');

        $expectedCells = $response->json('cells');
        $cellIds = collect($expectedCells)->pluck('id')->all();

        expect($cellIds)->toContain($this->cells->first()->id);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'cells_per_level' => 1,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $placement->id,
            'cell_id' => $this->cells->first()->id,
            'inventory_id' => $placement->inventory_id,
            'quantity' => $placement->quantity,
        ]);

        assertNotSoftDeleted('cells', [
            'id' => $this->cells->first()->id,
        ]);
    });

    it('restores archived cells when cells per level is increased again', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'cells_per_level' => 1,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(2, 'cells');

        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'cells_per_level' => 3,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(6, 'cells');

        $expectedCells = $response->json('cells');
        $expectedCellsCount = $response->json('levels_count') * $response->json('cells_per_level');
        $expectedCellsNumber = collect($expectedCells)->map(function ($cell) {
            return $cell['number'];
        })->all();

        expect($expectedCellsNumber)->toBe(range(1, $expectedCellsCount));

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'cells_per_level' => 3,
        ]);

        assertDatabaseCount('cells', $expectedCellsCount);

        foreach ($expectedCells as $expectedCell) {
            expect($expectedCell['level'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['level'])->toBeLessThanOrEqual(2)
                ->and($expectedCell['position'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeLessThanOrEqual(3);

            assertDatabaseHas('cells', [
                'id' => $expectedCell['id'],
                'number' => $expectedCell['number'],
                'level' => $expectedCell['level'],
                'position' => $expectedCell['position'],
            ]);
        }

        foreach ($this->cells as $cell) {
            assertNotSoftDeleted('cells', [
                'id' => $cell->id,
            ]);
        }
    });

    it('restores archived cells when removed levels are added back', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'levels_count' => 1,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(3, 'cells');

        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'levels_count' => 2,
        ]);

        $response->assertStatus(200);

        $response->assertJsonCount(6, 'cells');

        $expectedCells = $response->json('cells');
        $expectedCellsCount = $response->json('levels_count') * $response->json('cells_per_level');
        $expectedCellsNumber = collect($expectedCells)->map(function ($cell) {
            return $cell['number'];
        })->all();

        expect($expectedCellsNumber)->toBe(range(1, $expectedCellsCount));

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'levels_count' => 2,
        ]);

        assertDatabaseCount('cells', $expectedCellsCount);

        foreach ($expectedCells as $expectedCell) {
            expect($expectedCell['level'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['level'])->toBeLessThanOrEqual(2)
                ->and($expectedCell['position'])->toBeGreaterThanOrEqual(1)
                ->and($expectedCell['position'])->toBeLessThanOrEqual(3);

            assertDatabaseHas('cells', [
                'id' => $expectedCell['id'],
                'number' => $expectedCell['number'],
                'level' => $expectedCell['level'],
                'position' => $expectedCell['position'],
            ]);
        }

        foreach ($this->cells as $cell) {
            assertNotSoftDeleted('cells', [
                'id' => $cell->id,
            ]);
        }
    });

    it('rejects a code used by an archived rack in the warehouse', function () {
        $rack = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
            'code' => 'ST-01',
        ]);

        $rack->delete();

        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'code' => 'ST-01',
        ]);

        $response->assertStatus(422);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'code' => $this->rack->code,
        ]);
    });

    it('rejects removing cells that contain inventory placements', function () {
        $placement = InventoryPlacement::factory()->create([
            'cell_id' => $this->cells->last()->id,
        ]);

        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'cells_per_level' => 1,
        ]);

        $response->assertStatus(409);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'code' => $this->rack->code,
            'levels_count' => $this->rack->levels_count,
            'cells_per_level' => $this->rack->cells_per_level,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $placement->id,
            'cell_id' => $this->cells->last()->id,
            'inventory_id' => $placement->inventory_id,
            'quantity' => $placement->quantity,
        ]);

        foreach ($this->cells as $cell) {
            assertDatabaseHas('cells', [
                'id' => $cell->id,
                'number' => $cell->number,
                'level' => $cell->level,
                'position' => $cell->position,
                'active_marker' => 1,
            ]);
        }

        assertNotSoftDeleted('cells', [
            'id' => $this->cells->last()->id,
        ]);
    });

    it('rejects an invalid rack update payload', function (array $payload, string $errorKey, string $expectedMessage) {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => $expectedMessage,
            ]);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'code' => $this->rack->code,
            'levels_count' => $this->rack->levels_count,
            'cells_per_level' => $this->rack->cells_per_level,
        ]);

        assertDatabaseCount('cells', $this->cells->count());
    })->with([
        'code: не строка' => [
            ['code' => 12345],
            'code',
            'Код стеллажа должен быть текстовой строкой.',
        ],
        'code: слишком длинный' => [
            ['code' => str_repeat('A', 21)],
            'code',
            'Код стеллажа не должен превышать 20 символов.',
        ],
        'levels_count: не целое число' => [
            ['levels_count' => 'two'],
            'levels_count',
            'Количество уровней должно быть целым числом.',
        ],
        'levels_count: меньше минимума (0)' => [
            ['levels_count' => 0],
            'levels_count',
            'Стеллаж должен иметь как минимум 1 уровень.',
        ],
        'levels_count: превышает максимум (11)' => [
            ['levels_count' => 11],
            'levels_count',
            'Количество уровней не может превышать 10.',
        ],
        'cells_per_level: не целое число' => [
            ['cells_per_level' => 4.5],
            'cells_per_level',
            'Количество ячеек на уровне должно быть целым числом.',
        ],
        'cells_per_level: меньше минимума (0)' => [
            ['cells_per_level' => 0],
            'cells_per_level',
            'На уровне должна быть как минимум 1 ячейка.',
        ],
        'cells_per_level: превышает максимум (11)' => [
            ['cells_per_level' => 11],
            'cells_per_level',
            'Количество ячеек на уровне не может превышать 10.',
        ],
    ]);

    it('returns 404 when the rack does not exist', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id + 1000]), [
            'cells_per_level' => 4,
        ]);

        $response->assertStatus(404);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'cells_per_level' => $this->rack->cells_per_level,
        ]);

        assertDatabaseCount('cells', $this->cells->count());
    });

    it('returns 404 when the warehouse does not exist', function () {
        $response = $this->be($this->user)->patchJson(route('racks-update', [$this->warehouse->id + 1000, $this->rack->id]), [
            'cells_per_level' => 4,
        ]);

        $response->assertStatus(404);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'cells_per_level' => $this->rack->cells_per_level,
        ]);

        assertDatabaseCount('cells', $this->cells->count());
    });

    it('returns 404 when the rack belongs to another warehouse', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->be($this->user)->patchJson(route('racks-update', [$warehouse->id, $this->rack->id]), [
            'cells_per_level' => 4,
        ]);

        $response->assertStatus(404);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'cells_per_level' => $this->rack->cells_per_level,
        ]);

        assertDatabaseCount('cells', $this->cells->count());
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->patchJson(route('racks-update', [$this->warehouse->id, $this->rack->id]), [
            'cells_per_level' => 1,
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        assertDatabaseHas('racks', [
            'id' => $this->rack->id,
            'cells_per_level' => $this->rack->cells_per_level,
        ]);

        foreach ($this->cells as $cell) {
            assertNotSoftDeleted('cells', [
                'id' => $cell->id,
                'number' => $cell->number,
                'level' => $cell->level,
                'position' => $cell->position,
                'deleted_at' => null,
            ]);
        }
    });
});
