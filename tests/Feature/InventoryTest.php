<?php

use App\Models\Cell;
use App\Models\Inventory;
use App\Models\InventoryPlacement;
use App\Models\Rack;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

describe('Inventory creation', function () {
    beforeEach(function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        $this->user = User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->cell = Cell::factory()->create();

        $this->inventoryData = [
            'name' => 'Мяч',
            'description' => 'Футбольный',
            'quantity' => 3,
            'cell_id' => $this->cell->id,
        ];
    });

    it('creates inventory with an initial cell placement', function () {
        $response = $this->be($this->user)->postJson(route('inventory-create'), $this->inventoryData);

        $response->assertStatus(201);

        $response->assertJson([
            'id' => 1,
            'name' => 'Мяч',
            'description' => 'Футбольный',
            'quantity' => 3,
        ]);

        assertDatabaseCount('inventories', 1);

        assertDatabaseHas('inventories', [
            'id' => 1,
            'name' => 'Мяч',
            'description' => 'Футбольный',
            'quantity' => 3,
        ]);

        assertDatabaseCount('inventory_placements', 1);

        assertDatabaseHas('inventory_placements', [
            'inventory_id' => 1,
            'cell_id' => $this->cell->id,
            'quantity' => 3,
        ]);
    });

    it('creates unplaced inventory without optional fields', function () {
        $response = $this->be($this->user)->postJson(route('inventory-create'), [
            'name' => 'Мяч',
            'quantity' => 3,
        ]);

        $response->assertStatus(201);

        $response->assertJson([
            'id' => 1,
            'name' => 'Мяч',
            'description' => null,
            'quantity' => 3,
        ]);

        assertDatabaseCount('inventories', 1);

        assertDatabaseHas('inventories', [
            'id' => 1,
            'name' => 'Мяч',
            'description' => null,
            'quantity' => 3,
        ]);

        assertDatabaseCount('inventory_placements', 0);
    });

    it('accepts null values for optional fields', function () {
        $response = $this->be($this->user)->postJson(route('inventory-create'), [
            'name' => 'Мяч',
            'description' => null,
            'quantity' => 3,
            'cell_id' => null,
        ]);

        $response->assertStatus(201);

        $response->assertJson([
            'id' => 1,
            'name' => 'Мяч',
            'description' => null,
            'quantity' => 3,
        ]);

        assertDatabaseCount('inventories', 1);

        assertDatabaseHas('inventories', [
            'id' => 1,
            'name' => 'Мяч',
            'description' => null,
            'quantity' => 3,
        ]);

        assertDatabaseCount('inventory_placements', 0);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->postJson(route('inventory-create'), $this->inventoryData);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        assertDatabaseCount('inventories', 0);
        assertDatabaseCount('inventory_placements', 0);
    });

    it('rejects a nonexistent cell and rolls back inventory creation', function () {
        $response = $this->be($this->user)->postJson(route('inventory-create'), [
            'name' => 'Мяч',
            'description' => 'Футбольный',
            'quantity' => 3,
            'cell_id' => $this->cell->id + 1000,
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Ячейка с переданным id не найдена',
        ]);

        assertDatabaseCount('inventories', 0);

        assertDatabaseCount('inventory_placements', 0);
    });

    it('rejects a duplicate inventory name', function () {
        $name = 'Мяч';

        Inventory::factory()->create([
            'name' => $name,
        ]);

        $response = $this->be($this->user)->postJson(route('inventory-create'), $this->inventoryData);

        $response->assertStatus(422);

        $response->assertJson([
            'message' => 'Название предмета уже занято',
            'errors' => [
                'name' => ['Название предмета уже занято'],
            ],
        ]);

        assertDatabaseCount('inventories', 1);
    });

    it('rejects invalid inventory data', function (array $payload, string $errorKey, string $expectedMessage) {
        $response = $this->be($this->user)->postJson(route('inventory-create'), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => $expectedMessage,
            ]);

        assertDatabaseCount('inventories', 0);
    })->with([
        'name is required' => [
            ['quantity' => 10],
            'name',
            'Пожалуйста, укажите наименование предмета.',
        ],
        'name is not a string' => [
            ['name' => 12345, 'quantity' => 10],
            'name',
            'Наименование предмета должно быть строкой.',
        ],
        'name exceeds max length' => [
            ['name' => str_repeat('A', 256), 'quantity' => 10],
            'name',
            'Наименование предмета не должно превышать 255 символов.',
        ],
        'description is not a string' => [
            ['name' => 'Товар', 'quantity' => 10, 'description' => 12345],
            'description',
            'Дополнительное описание должно быть строкой.',
        ],
        'description exceeds max length' => [
            ['name' => 'Товар', 'quantity' => 10, 'description' => str_repeat('B', 256)],
            'description',
            'Дополнительное описание не должно превышать 255 символов.',
        ],
        'quantity is required' => [
            ['name' => 'Товар'],
            'quantity',
            'Пожалуйста, укажите количество единиц предмета.',
        ],
        'quantity is not an integer' => [
            ['name' => 'Товар', 'quantity' => 'ten'],
            'quantity',
            'Количество единиц предмета должно быть целым числом.',
        ],
        'quantity is less than 1' => [
            ['name' => 'Товар', 'quantity' => 0],
            'quantity',
            'Количество единиц предмета должно быть не менее 1.',
        ],
        'cell_id is not an integer' => [
            ['name' => 'Товар', 'quantity' => 10, 'cell_id' => 'first-cell'],
            'cell_id',
            'Идентификатор ячейки должен быть целым числом.',
        ],
        'cell_id is less than 1' => [
            ['name' => 'Товар', 'quantity' => 10, 'cell_id' => 0],
            'cell_id',
            'Идентификатор ячейки должен быть не менее 1.',
        ],
    ]);
});

describe('Inventory update', function () {
    beforeEach(function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        $this->user = User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->inventory = Inventory::factory()->create([
            'name' => 'Название',
            'description' => 'Какое-то описание',
            'quantity' => 1,
        ]);
    });

    it('updates the inventory name and description', function () {
        $response = $this->be($this->user)->patchJson(route('inventory-update', $this->inventory->id), [
            'name' => 'Гвозди',
            'description' => 'Какое-то описание',
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->inventory->id,
            'name' => 'Гвозди',
            'description' => 'Какое-то описание',
            'quantity' => $this->inventory->quantity,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'name' => 'Гвозди',
            'description' => 'Какое-то описание',
            'quantity' => $this->inventory->quantity,
        ]);
    });

    it('does not persist an update when submitted values are unchanged', function () {
        $originalUpdatedAt = $this->inventory->updated_at->toIso8601String();

        $this->travel(5)->minute();

        $response = $this->be($this->user)->patchJson(route('inventory-update', $this->inventory->id), [
            'name' => $this->inventory->name,
            'description' => $this->inventory->description,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
        ]);

        $this->inventory->refresh();

        expect($this->inventory->updated_at->toIso8601String())->toBe($originalUpdatedAt);
    });

    it('updates only the inventory name', function () {
        $response = $this->be($this->user)->patchJson(route('inventory-update', $this->inventory->id), [
            'name' => 'Гвозди',
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->inventory->id,
            'name' => 'Гвозди',
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'name' => 'Гвозди',
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
        ]);
    });

    it('updates only the inventory description', function () {
        $response = $this->be($this->user)->patchJson(route('inventory-update', $this->inventory->id), [
            'description' => 'Какое-то описание',
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => 'Какое-то описание',
            'quantity' => $this->inventory->quantity,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => 'Какое-то описание',
            'quantity' => $this->inventory->quantity,
        ]);
    });

    it('clears the inventory description when null is provided', function () {
        $response = $this->be($this->user)->patchJson(route('inventory-update', $this->inventory->id), [
            'description' => null,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => null,
            'quantity' => $this->inventory->quantity,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => null,
            'quantity' => $this->inventory->quantity,
        ]);
    });

    it('rejects a request without update fields', function () {
        $originalUpdatedAt = $this->inventory->updated_at->toIso8601String();

        $this->travel(5)->minute();

        $response = $this->be($this->user)->patchJson(route('inventory-update', $this->inventory->id), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'request' => 'Должно быть передано хотя бы одно поле',
            ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
        ]);

        $this->inventory->refresh();

        expect($this->inventory->updated_at->toIso8601String())->toBe($originalUpdatedAt);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->patchJson(route('inventory-update', $this->inventory->id), [
            'name' => 'Гвозди',
            'description' => null,
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
        ]);
    });

    it('rejects a duplicate inventory name without applying other changes', function () {
        $existingInventoryName = 'Пила';
        Inventory::factory()->create([
            'name' => $existingInventoryName,
        ]);

        $response = $this->be($this->user)->patchJson(route('inventory-update', $this->inventory->id), [
            'name' => $existingInventoryName,
            'description' => null,
        ]);

        $response->assertStatus(422);

        $response->assertJson([
            'message' => 'Название предмета уже занято',
            'errors' => [
                'name' => ['Название предмета уже занято'],
            ],
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
        ]);
    });

    it('returns not found for a nonexistent inventory', function () {
        $response = $this->be($this->user)->patchJson(route('inventory-update', $this->inventory->id + 1000), [
            'name' => 'Гвозди',
            'description' => null,
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Предмет с переданным id не найден',
        ]);
    });

    it('rejects invalid inventory update data', function (array $payload, string $errorKey, string $expectedMessage) {
        $response = $this->be($this->user)->patchJson(route('inventory-update', $this->inventory->id), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => $expectedMessage,
            ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
        ]);
    })->with([
        'name is null' => [
            ['name' => null],
            'name',
            'Наименование предмета должно быть строкой.',
        ],
        'name is not a string' => [
            ['name' => 12345],
            'name',
            'Наименование предмета должно быть строкой.',
        ],
        'name exceeds max length' => [
            ['name' => str_repeat('A', 256)],
            'name',
            'Наименование предмета не должно превышать 255 символов.',
        ],
        'description is not a string' => [
            ['description' => 12345],
            'description',
            'Дополнительное описание должно быть строкой.',
        ],
        'description exceeds max length' => [
            ['description' => str_repeat('B', 256)],
            'description',
            'Дополнительное описание не должно превышать 255 символов.',
        ],
    ]);
});

describe('Inventory show', function () {
    beforeEach(function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        $this->user = User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->inventory = Inventory::factory()->create([
            'name' => 'Название',
            'description' => 'Какое-то описание',
            'quantity' => 3,
        ]);

        $this->inventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => 1,
        ]);
    });

    it('shows inventory with its placement', function () {
        $response = $this->be($this->user)->getJson(route('inventory-show', $this->inventory->id));

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
            'placements' => [
                [
                    'id' => $this->inventoryPlacement->id,
                    'cell_id' => $this->inventoryPlacement->cell_id,
                    'quantity' => $this->inventoryPlacement->quantity,
                ],
            ],
        ]);

        $response->assertJsonCount(1, 'placements');

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->inventoryPlacement->cell_id,
            'quantity' => $this->inventoryPlacement->quantity,
        ]);
    });

    it('shows inventory with multiple placements', function () {
        $secondPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => 1,
        ]);

        $response = $this->be($this->user)->getJson(route('inventory-show', $this->inventory->id));

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->inventory->id,
            'name' => $this->inventory->name,
            'description' => $this->inventory->description,
            'quantity' => $this->inventory->quantity,
            'placements' => [
                [
                    'id' => $this->inventoryPlacement->id,
                    'cell_id' => $this->inventoryPlacement->cell_id,
                    'quantity' => $this->inventoryPlacement->quantity,
                ],
                [
                    'id' => $secondPlacement->id,
                    'cell_id' => $secondPlacement->cell_id,
                    'quantity' => $secondPlacement->quantity,
                ],
            ],
        ]);

        $response->assertJsonCount(2, 'placements');
    });

    it('shows inventory without placements', function () {
        $inventory = Inventory::factory()->create([
            'name' => 'Клюшка',
            'description' => null,
        ]);

        $response = $this->be($this->user)->getJson(route('inventory-show', $inventory->id));

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $inventory->id,
            'name' => $inventory->name,
            'description' => null,
            'quantity' => $inventory->quantity,
            'placements' => [],
        ]);

        $response->assertJsonCount(0, 'placements');
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->getJson(route('inventory-show', $this->inventory->id));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });

    it('returns not found for a nonexistent inventory', function () {
        $response = $this->be($this->user)->getJson(route('inventory-show', $this->inventory->id + 1000));

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Предмет с переданным id не найден',
        ]);
    });
});

describe('Inventory index', function () {
    beforeEach(function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        $this->user = User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
    });

    it('returns inventories with placement quantities and locations', function () {
        $inventory = Inventory::factory()->create([
            'name' => 'Гвозди',
            'description' => 'Описание',
            'quantity' => 3,
        ]);

        $inventoryPlacements = InventoryPlacement::factory()->count(2)->create([
            'inventory_id' => $inventory->id,
            'quantity' => 1,
        ]);

        $response = $this->be($this->user)->getJson(route('inventory-index'));

        $response->assertStatus(200);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'quantity',
                    'placed_quantity',
                    'unplaced_quantity',
                    'placements' => [
                        '*' => [
                            'id',
                            'quantity',

                            'warehouse' => ['id', 'name'],
                            'rack' => ['id', 'code'],
                            'cell' => ['id', 'number', 'level', 'position'],
                        ],
                    ],
                ],
            ],
        ]);

        $expectedPlacementIds = $inventoryPlacements->pluck('id')->sort()->values()->all();
        $inventoryPlacedQuantity = $inventoryPlacements->sum('quantity');
        $inventoryUnplacedQuantity = $inventory->quantity - $inventoryPlacedQuantity;

        $responseData = collect($response->json('data'));
        $actualInventory = $responseData->where('id', $inventory->id)->first();
        $actualPlacementIds = collect($actualInventory['placements'])->pluck('id')->all();

        expect($actualInventory['name'])->toBe($inventory->name)
            ->and($actualInventory['description'])->toBe($inventory->description)
            ->and($actualInventory['quantity'])->toBe($inventory->quantity)
            ->and($actualInventory['placed_quantity'])->toBe($inventoryPlacedQuantity)
            ->and($actualInventory['unplaced_quantity'])->toBe($inventoryUnplacedQuantity)
            ->and($actualPlacementIds)->toBe($expectedPlacementIds);

        foreach ($actualInventory['placements'] as $actualPlacement) {
            $expectedPlacement = $inventoryPlacements->firstWhere('id', $actualPlacement['id']);

            $expectedCell = $expectedPlacement->cell;
            $expectedRack = $expectedCell->rack;
            $expectedWarehouse = $expectedRack->warehouse;

            expect($actualPlacement['id'])->toBe($expectedPlacement->id)
                ->and($actualPlacement['quantity'])->toBe($expectedPlacement->quantity)
                ->and($actualPlacement['cell']['id'])->toBe($expectedCell->id)
                ->and($actualPlacement['cell']['number'])->toBe($expectedCell->number)
                ->and($actualPlacement['cell']['level'])->toBe($expectedCell->level)
                ->and($actualPlacement['cell']['position'])->toBe($expectedCell->position)
                ->and($actualPlacement['rack']['id'])->toBe($expectedRack->id)
                ->and($actualPlacement['rack']['code'])->toBe($expectedRack->code)
                ->and($actualPlacement['warehouse']['id'])->toBe($expectedWarehouse->id)
                ->and($actualPlacement['warehouse']['name'])->toBe($expectedWarehouse->name);
        }
    });

    it('returns a fully unplaced inventory', function () {
        $inventory = Inventory::factory()->create([
            'name' => 'Гвозди',
            'description' => null,
            'quantity' => 3,
        ]);

        $response = $this->be($this->user)->getJson(route('inventory-index'));

        $response->assertStatus(200);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'quantity',
                    'placed_quantity',
                    'unplaced_quantity',
                    'placements' => [],
                ],
            ],
        ]);

        $responseData = collect($response->json('data'));
        $actualInventory = $responseData->where('id', $inventory->id)->first();
        $inventoryPlacedQuantity = $inventory->inventoryPlacements->sum('quantity');
        $inventoryUnplacedQuantity = $inventory->quantity - $inventoryPlacedQuantity;

        expect($actualInventory['unplaced_quantity'])->toBe($inventoryUnplacedQuantity)
            ->and($actualInventory['placed_quantity'])->toBe($inventoryPlacedQuantity)
            ->and($actualInventory['description'])->toBeNull()
            ->and($actualInventory['placements'])->toHaveCount($inventory->inventoryPlacements->count());
    });

    it('returns a fully placed inventory', function () {
        $inventory = Inventory::factory()->create([
            'name' => 'Гвозди',
            'description' => null,
            'quantity' => 2,
        ]);

        $inventoryPlacements = InventoryPlacement::factory()->count(2)->create([
            'inventory_id' => $inventory->id,
            'quantity' => 1,
        ]);

        $response = $this->be($this->user)->getJson(route('inventory-index'));

        $response->assertStatus(200);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'quantity',
                    'placed_quantity',
                    'unplaced_quantity',
                    'placements' => [
                        '*' => [
                            'id',
                            'quantity',

                            'warehouse' => ['id', 'name'],
                            'rack' => ['id', 'code'],
                            'cell' => ['id', 'number', 'level', 'position'],
                        ],
                    ],
                ],
            ],
        ]);

        $responseData = collect($response->json('data'));
        $actualInventory = $responseData->where('id', $inventory->id)->first();
        $inventoryPlacedQuantity = $inventoryPlacements->sum('quantity');
        $inventoryUnplacedQuantity = $inventory->quantity - $inventoryPlacedQuantity;

        expect($actualInventory['unplaced_quantity'])->toBe($inventoryUnplacedQuantity)
            ->and($actualInventory['placed_quantity'])->toBe($inventoryPlacedQuantity)
            ->and($actualInventory['description'])->toBeNull()
            ->and($actualInventory['placements'])->toHaveCount($inventoryPlacements->count());
    });

    it('returns inventories ordered by id ascending', function () {
        $inventories = Inventory::factory()->count(3)->create();

        $response = $this->be($this->user)->getJson(route('inventory-index'));

        $response->assertStatus(200);

        $response->assertJsonCount(3, 'data');

        $expectedInventoryIds = $inventories->pluck('id')->sort()->values()->all();

        $responseData = collect($response->json('data'));
        $actualInventoryIds = $responseData->pluck('id')->all();

        expect($actualInventoryIds)->toBe($expectedInventoryIds);
    });

    it('returns an empty list when no inventories exist', function () {
        $response = $this->be($this->user)->getJson(route('inventory-index'));

        $response->assertStatus(200);

        $response->assertJson([
            'data' => [],
        ]);

        $response->assertJsonCount(0, 'data');
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->getJson(route('inventory-index'));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});

describe('Inventory by warehouse index', function () {
    beforeEach(function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        $this->user = User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->warehouse = Warehouse::factory()->create();
    });

    it('returns inventories with placement quantities and locations', function () {
        $rack1 = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        $rack2 = Rack::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        $cell1 = Cell::factory()->create([
            'rack_id' => $rack1->id,
        ]);
        $cell2 = Cell::factory()->create([
            'rack_id' => $rack2->id,
        ]);

        $inventory = Inventory::factory()->create([
            'name' => 'Гвозди',
            'description' => 'Описание',
            'quantity' => 10,
        ]);

        $mainPlacements = collect([$cell1, $cell2])->map(fn (Cell $cell) => InventoryPlacement::factory()->create([
            'inventory_id' => $inventory->id,
            'cell_id' => $cell->id,
            'quantity' => 2,
        ])
        );

        $anotherPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $inventory->id,
            'quantity' => 3,
        ]);

        $response = $this->be($this->user)->getJson(route('warehouses-inventories-index', $this->warehouse->id));

        $response->assertStatus(200);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'quantity',
                    'quantity_in_warehouse',
                    'placements' => [
                        '*' => [
                            'id',
                            'quantity',

                            'rack' => ['id', 'code'],
                            'cell' => ['id', 'number', 'level', 'position'],
                        ],
                    ],
                ],
            ],
        ]);

        $response->assertJsonMissingPath('data.*.placements.*.warehouse');

        $quantityInWarehouse = $mainPlacements->sum('quantity');
        $mainPlacementIds = $mainPlacements->pluck('id')->all();
        $mainPlacementQuantities = $mainPlacements->pluck('quantity')->all();

        $responseData = collect($response->json('data'));
        $actualInventory = $responseData->where('id', $inventory->id)->first();
        $actualPlacementIds = collect($actualInventory['placements'])->pluck('id')->all();
        $actualRackIds = collect($actualInventory['placements'])->pluck('rack.id')->all();
        $actualRackCodes = collect($actualInventory['placements'])->pluck('rack.code')->all();
        $actualCellIds = collect($actualInventory['placements'])->pluck('cell.id')->all();
        $actualCellNumbers = collect($actualInventory['placements'])->pluck('cell.number')->all();
        $actualCellLevels = collect($actualInventory['placements'])->pluck('cell.level')->all();
        $actualCellPositions = collect($actualInventory['placements'])->pluck('cell.position')->all();
        $actualPlacementQuantities = collect($actualInventory['placements'])->pluck('quantity')->all();

        expect($actualInventory['quantity_in_warehouse'])->toBe($quantityInWarehouse)
            ->and($actualInventory['name'])->toBe($inventory->name)
            ->and($actualInventory['description'])->toBe($inventory->description)
            ->and($actualInventory['quantity'])->toBe($inventory->quantity)
            ->and($actualInventory['placements'])->toHaveCount($mainPlacements->count())
            ->and($actualPlacementIds)->toBe($mainPlacementIds)
            ->and($actualPlacementIds)->not->toContain($anotherPlacement->id)
            ->and($actualPlacementQuantities)->toBe($mainPlacementQuantities)
            ->and($actualRackIds)->toBe([$rack1->id, $rack2->id])
            ->and($actualRackCodes)->toBe([$rack1->code, $rack2->code])
            ->and($actualCellIds)->toBe([$cell1->id, $cell2->id])
            ->and($actualCellNumbers)->toBe([$cell1->number, $cell2->number])
            ->and($actualCellLevels)->toBe([$cell1->level, $cell2->level])
            ->and($actualCellPositions)->toBe([$cell1->position, $cell2->position]);
    });

    it('returns inventories ordered by id', function () {
        $inventories = Inventory::factory()
            ->count(2)
            ->has(
                InventoryPlacement::factory()->state([
                    'cell_id' => Cell::factory()->state([
                        'rack_id' => Rack::factory()->state(['warehouse_id' => $this->warehouse->id]),
                    ]),
                    'quantity' => 5,
                ]),
                'inventoryPlacements'
            )
            ->create([
                'quantity' => 5,
            ]);

        $expectedInventoryIds = $inventories->pluck('id')->sort()->values()->all();

        $response = $this->be($this->user)->getJson(route('warehouses-inventories-index', $this->warehouse->id));

        $response->assertStatus(200);

        $response->assertJsonCount(2, 'data');

        $responseData = collect($response->json('data'));
        $actualInventoryIds = $responseData->pluck('id')->all();

        expect($actualInventoryIds)->toBe($expectedInventoryIds);
    });

    it('excludes inventory placed only in another warehouse', function () {
        $inventory = Inventory::factory()->create([
            'name' => 'Гвозди',
            'description' => 'Описание',
            'quantity' => 1,
        ]);

        InventoryPlacement::factory()->create([
            'inventory_id' => $inventory->id,
        ]);

        $response = $this->be($this->user)->getJson(route('warehouses-inventories-index', $this->warehouse->id));

        $response->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    });

    it('returns not found when the warehouse does not exist', function () {
        $response = $this->be($this->user)->getJson(route('warehouses-inventories-index', $this->warehouse->id + 1000));

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Склад с передаваемым id не найден',
        ]);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->getJson(route('warehouses-inventories-index', $this->warehouse->id));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});

describe('Inventory by cell index', function () {
    beforeEach(function () {
        $name = 'Name';
        $email = 'test@test.com';
        $password = 'password1234';

        $this->user = User::factory()->createOne([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        $this->cell = Cell::factory()->create();
    });

    it('returns inventories placed in the requested cell', function () {
        $inventory = Inventory::factory()->create([
            'name' => 'Гвозди',
            'description' => 'Описание',
            'quantity' => 5,
        ]);

        $inventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 3,
        ]);

        InventoryPlacement::factory()->create([
            'inventory_id' => $inventory->id,
            'quantity' => 2,
        ]);

        $inventory2 = Inventory::factory()->create([
            'quantity' => 5,
        ]);

        InventoryPlacement::factory()->create([
            'inventory_id' => $inventory2->id,
        ]);

        $response = $this->be($this->user)->getJson(route('cell-inventories-index', $this->cell->id));

        $response->assertStatus(200);

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'name',
                    'description',
                    'quantity',
                    'quantity_in_cell',
                    'placement_id',
                ],
            ],
        ]);

        $response->assertJsonCount(1, 'data');

        $response->assertJsonMissing([
            'id' => $inventory2->id,
        ]);

        $responseData = collect($response->json('data'));
        $actualInventory = $responseData->where('id', $inventory->id)->first();

        expect($actualInventory['id'])->toBe($inventory->id)
            ->and($actualInventory['name'])->toBe($inventory->name)
            ->and($actualInventory['description'])->toBe($inventory->description)
            ->and($actualInventory['quantity'])->toBe($inventory->quantity)
            ->and($actualInventory['quantity_in_cell'])->toBe($inventoryPlacement->quantity)
            ->and($actualInventory['placement_id'])->toBe($inventoryPlacement->id);
    });

    it('returns inventories ordered by id ascending', function () {
        $inventories = Inventory::factory()->count(2)->create([
            'quantity' => 5,
        ]);

        $inventories->each(fn (Inventory $inventory) => InventoryPlacement::factory()->create([
            'inventory_id' => $inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 2,
        ])
        );

        $response = $this->be($this->user)->getJson(route('cell-inventories-index', $this->cell->id));

        $response->assertStatus(200);

        $responseData = collect($response->json('data'));
        $actualInventoryIds = $responseData->pluck('id')->all();
        $expectedInventoryIds = $inventories->pluck('id')->sort()->values()->all();

        expect($actualInventoryIds)->toBe($expectedInventoryIds);
    });

    it('excludes inventory placed only in another cell', function () {
        $inventory = Inventory::factory()->create([
            'name' => 'Гвозди',
            'description' => 'Описание',
            'quantity' => 1,
        ]);

        InventoryPlacement::factory()->create([
            'inventory_id' => $inventory->id,
        ]);

        $response = $this->be($this->user)->getJson(route('cell-inventories-index', $this->cell->id));

        $response->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    });

    it('returns not found when the cell does not exist', function () {
        $response = $this->be($this->user)->getJson(route('cell-inventories-index', $this->cell->id + 1000));

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Ячейка с переданным id не найдена',
        ]);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->getJson(route('cell-inventories-index', $this->cell->id));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});
