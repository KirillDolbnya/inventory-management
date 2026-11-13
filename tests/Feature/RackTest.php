<?php

use App\Models\Rack;
use App\Models\User;
use App\Repositories\WarehouseRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;

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
