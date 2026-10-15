<?php

use App\Models\Cell;
use App\Models\Inventory;
use App\Models\InventoryPlacement;
use App\Models\User;
use App\Repositories\InventoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

describe('Inventory transfer', function () {
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
            'name' => 'Гвозди',
            'description' => null,
            'quantity' => 10,
        ]);

        $this->cell = Cell::factory()->create();

        $this->inventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => 5,
        ]);
    });

    it('partially transfers inventory to an empty target cell', function () {
        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), [
            'target_cell_id' => $this->cell->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(200);

        $targetPlacementId = $response->json('target_placement.id');

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'transferred_quantity' => 1,
            'source_placement' => [
                'id' => $this->inventoryPlacement->id,
                'cell_id' => $this->inventoryPlacement->cell_id,
                'quantity' => 4,
            ],
            'target_placement' => [
                'id' => $targetPlacementId,
                'cell_id' => $this->cell->id,
                'quantity' => 1,
            ],
            'placed_quantity' => 5,
            'unplaced_quantity' => 5,
        ]);

        assertDatabaseCount('inventory_placements', 2);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->inventoryPlacement->cell_id,
            'quantity' => 4,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $targetPlacementId,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 1,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'quantity' => 10,
        ]);
    });

    it('fully transfers inventory to an empty target cell', function () {
        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), [
            'target_cell_id' => $this->cell->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(200);

        $targetPlacementId = $response->json('target_placement.id');

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'transferred_quantity' => 5,
            'source_placement' => null,
            'target_placement' => [
                'id' => $targetPlacementId,
                'cell_id' => $this->cell->id,
                'quantity' => 5,
            ],
            'placed_quantity' => 5,
            'unplaced_quantity' => 5,
        ]);

        assertDatabaseCount('inventory_placements', 1);

        assertDatabaseMissing('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $targetPlacementId,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 5,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'quantity' => 10,
        ]);
    });

    it('partially transfers inventory into an existing target placement', function () {
        $targetInventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 2,
        ]);

        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), [
            'target_cell_id' => $this->cell->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'transferred_quantity' => 2,
            'source_placement' => [
                'id' => $this->inventoryPlacement->id,
                'cell_id' => $this->inventoryPlacement->cell_id,
                'quantity' => 3,
            ],
            'target_placement' => [
                'id' => $targetInventoryPlacement->id,
                'cell_id' => $this->cell->id,
                'quantity' => 4,
            ],
            'placed_quantity' => 7,
            'unplaced_quantity' => 3,
        ]);

        assertDatabaseCount('inventory_placements', 2);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->inventoryPlacement->cell_id,
            'quantity' => 3,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $targetInventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 4,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'quantity' => 10,
        ]);
    });

    it('fully transfers inventory into an existing target placement', function () {
        $targetInventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 2,
        ]);

        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), [
            'target_cell_id' => $this->cell->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'transferred_quantity' => 5,
            'source_placement' => null,
            'target_placement' => [
                'id' => $targetInventoryPlacement->id,
                'cell_id' => $this->cell->id,
                'quantity' => 7,
            ],
            'placed_quantity' => 7,
            'unplaced_quantity' => 3,
        ]);

        assertDatabaseCount('inventory_placements', 1);

        assertDatabaseMissing('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $targetInventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 7,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'quantity' => 10,
        ]);
    });

    it('keeps the source placement when transferring exactly half its quantity', function () {
        $this->inventoryPlacement->update([
            'quantity' => 4,
        ]);

        $targetInventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 2,
        ]);

        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), [
            'target_cell_id' => $this->cell->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'transferred_quantity' => 2,
            'source_placement' => [
                'id' => $this->inventoryPlacement->id,
                'cell_id' => $this->inventoryPlacement->cell_id,
                'quantity' => 2,
            ],
            'target_placement' => [
                'id' => $targetInventoryPlacement->id,
                'cell_id' => $this->cell->id,
                'quantity' => 4,
            ],
            'placed_quantity' => 6,
            'unplaced_quantity' => 4,
        ]);

        assertDatabaseCount('inventory_placements', 2);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->inventoryPlacement->cell_id,
            'quantity' => 2,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $targetInventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 4,
        ]);

        assertDatabaseHas('inventories', [
            'id' => $this->inventory->id,
            'quantity' => 10,
        ]);
    });

    it('rejects invalid transfer data', function (array $payload, string $errorKey, string $expectedMessage) {
        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => [$expectedMessage],
            ]);

        assertDatabaseCount('inventory_placements', 1);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => $this->inventoryPlacement->quantity,
        ]);
    })->with([
        'target_cell_id_is_required' => [
            'payload' => ['quantity' => 5],
            'errorKey' => 'target_cell_id',
            'expectedMessage' => 'Идентификатор целевой ячейки обязателен для заполнения.',
        ],
        'target_cell_id_must_be_an_integer' => [
            'payload' => ['target_cell_id' => 'строка', 'quantity' => 5],
            'errorKey' => 'target_cell_id',
            'expectedMessage' => 'Идентификатор целевой ячейки должен быть целым числом.',
        ],
        'target_cell_id_cannot_be_less_than_one' => [
            'payload' => ['target_cell_id' => 0, 'quantity' => 5],
            'errorKey' => 'target_cell_id',
            'expectedMessage' => 'Идентификатор целевой ячейки должен быть не менее 1.',
        ],
        'quantity_is_required' => [
            'payload' => ['target_cell_id' => 2],
            'errorKey' => 'quantity',
            'expectedMessage' => 'Количество предмета обязательно для заполнения.',
        ],
        'quantity_must_be_an_integer' => [
            'payload' => ['target_cell_id' => 2, 'quantity' => 'две_штуки'],
            'errorKey' => 'quantity',
            'expectedMessage' => 'Количество предмета должно быть целым числом.',
        ],
        'quantity_cannot_be_less_than_one' => [
            'payload' => ['target_cell_id' => 2, 'quantity' => 0],
            'errorKey' => 'quantity',
            'expectedMessage' => 'Количество предмета должно быть не менее 1 шт.',
        ],
    ]);

    it('returns not found when the inventory placement does not exist', function () {
        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id + 1000), [
            'target_cell_id' => $this->cell->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Размещение товара в ячейке не найдено.',
        ]);
    });

    it('returns not found when the inventory does not exist', function () {
        $this->mock(InventoryRepository::class)
            ->makePartial()
            ->shouldReceive('getByIdForUpdate')
            ->with($this->inventory->id)
            ->andReturn(null);

        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), [
            'target_cell_id' => $this->cell->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Предмет с переданным id не найден',
        ]);
    });

    it('rejects a transfer exceeding the source placement quantity', function () {
        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), [
            'target_cell_id' => $this->cell->id,
            'quantity' => 6,
        ]);

        $response->assertStatus(422);

        $response->assertJson([
            'message' => 'Запрашиваемое количество 6 превышает фактический остаток в данном месте хранения. Доступно для перемещения: 5 шт.',
            'errors' => [
                'quantity' => ['Запрашиваемое количество 6 превышает фактический остаток в данном месте хранения. Доступно для перемещения: 5 шт.'],
            ],
        ]);

        assertDatabaseCount('inventory_placements', 1);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->inventoryPlacement->cell_id,
            'quantity' => 5,
        ]);
    });

    it('returns not found when the cell does not exist', function () {
        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), [
            'target_cell_id' => $this->cell->id + 111,
            'quantity' => 1,
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Ячейка с переданным id не найдена',
        ]);
    });

    it('rejects a transfer to the source cell', function () {
        $response = $this->be($this->user)->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), [
            'target_cell_id' => $this->inventoryPlacement->cell_id,
            'quantity' => 1,
        ]);

        $response->assertStatus(422);

        $response->assertJson([
            'message' => 'Исходная и целевая ячейки совпадают. Перемещение предмета внутри одной и той же ячейки невозможно.',
            'errors' => [
                'target_cell_id' => ['Исходная и целевая ячейки совпадают. Перемещение предмета внутри одной и той же ячейки невозможно.'],
            ],
        ]);

        assertDatabaseCount('inventory_placements', 1);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->inventoryPlacement->cell_id,
            'quantity' => 5,
        ]);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->postJson(route('inventory-placements-transfer', $this->inventoryPlacement->id), [
            'target_cell_id' => $this->cell->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });

});
