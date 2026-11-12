<?php

use App\Models\Cell;
use App\Models\Inventory;
use App\Models\InventoryPlacement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

describe('Inventory placement', function () {
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
    });

    it('places part of an inventory in a cell', function () {
        $response = $this->be($this->user)->postJson(route('inventory-place', $this->inventory->id), [
            'cell_id' => $this->cell->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'inventory_quantity' => $this->inventory->quantity,
            'placement_quantity' => 5,
            'placed_quantity' => 5,
            'unplaced_quantity' => 5,
        ]);

        $responsePlacementId = $response->json('id');

        assertDatabaseHas('inventory_placements', [
            'id' => $responsePlacementId,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 5,
        ]);
    });

    it('places all available inventory in a cell', function () {
        $response = $this->be($this->user)->postJson(route('inventory-place', $this->inventory->id), [
            'cell_id' => $this->cell->id,
            'quantity' => 10,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'inventory_quantity' => $this->inventory->quantity,
            'placement_quantity' => 10,
            'placed_quantity' => 10,
            'unplaced_quantity' => 0,
        ]);

        $responsePlacementId = $response->json('id');

        assertDatabaseHas('inventory_placements', [
            'id' => $responsePlacementId,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 10,
        ]);
    });

    it('places inventory in another cell and calculates aggregate quantities', function () {
        $inventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => 3,
        ]);

        $response = $this->be($this->user)->postJson(route('inventory-place', $this->inventory->id), [
            'cell_id' => $this->cell->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'inventory_quantity' => 10,
            'placement_quantity' => 2,
            'placed_quantity' => 5,
            'unplaced_quantity' => 5,
        ]);

        $responsePlacementId = $response->json('id');

        assertDatabaseCount('inventory_placements', 2);

        assertDatabaseHas('inventory_placements', [
            'id' => $responsePlacementId,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 2,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => 3,
        ]);
    });

    it('increments an existing placement in the same cell', function () {
        $inventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 2,
        ]);

        $response = $this->be($this->user)->postJson(route('inventory-place', $this->inventory->id), [
            'cell_id' => $this->cell->id,
            'quantity' => 2,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'inventory_quantity' => 10,
            'placement_quantity' => 4,
            'placed_quantity' => 4,
            'unplaced_quantity' => 6,
        ]);

        assertDatabaseCount('inventory_placements', 1);

        assertDatabaseHas('inventory_placements', [
            'id' => $inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $this->cell->id,
            'quantity' => 4,
        ]);
    });

    it('rejects a quantity greater than the available remainder', function () {
        $cell = Cell::factory()->create();

        $inventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'cell_id' => $cell->id,
            'quantity' => 7,
        ]);

        $response = $this->be($this->user)->postJson(route('inventory-place', $this->inventory->id), [
            'cell_id' => $this->cell->id,
            'quantity' => 4,
        ]);

        $response->assertStatus(422);

        $response->assertJson([
            'message' => 'Запрашиваемое количество 4 превышает свободный остаток предмета. Доступно для размещения: 3 шт.',
            'errors' => [
                'quantity' => ['Запрашиваемое количество 4 превышает свободный остаток предмета. Доступно для размещения: 3 шт.'],
            ],
        ]);

        assertDatabaseCount('inventory_placements', 1);

        assertDatabaseHas('inventory_placements', [
            'id' => $inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'cell_id' => $cell->id,
            'quantity' => $inventoryPlacement->quantity,
        ]);
    });

    it('rejects invalid placement data', function (array $payload, string $errorKey, string $expectedMessage) {
        $response = $this->be($this->user)->postJson(route('inventory-place', $this->inventory->id), $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                $errorKey => [$expectedMessage],
            ]);
    })->with([
        'cell_id_required' => [
            'payload' => ['quantity' => 10],
            'errorKey' => 'cell_id',
            'expectedMessage' => 'Идентификатор ячейки обязателен для заполнения.',
        ],
        'cell_id_not_integer' => [
            'payload' => ['cell_id' => 'строка', 'quantity' => 10],
            'errorKey' => 'cell_id',
            'expectedMessage' => 'Идентификатор ячейки должен быть целым числом.',
        ],
        'cell_id_less_than_min' => [
            'payload' => ['cell_id' => 0, 'quantity' => 10],
            'errorKey' => 'cell_id',
            'expectedMessage' => 'Идентификатор ячейки должен быть не менее 1.',
        ],
        'quantity_required' => [
            'payload' => ['cell_id' => 5],
            'errorKey' => 'quantity',
            'expectedMessage' => 'Количество предмета обязательно для заполнения.',
        ],
        'quantity_not_integer' => [
            'payload' => ['cell_id' => 5, 'quantity' => 'две_штуки'],
            'errorKey' => 'quantity',
            'expectedMessage' => 'Количество предмета должно быть целым числом.',
        ],
        'quantity_less_than_min' => [
            'payload' => ['cell_id' => 5, 'quantity' => 0],
            'errorKey' => 'quantity',
            'expectedMessage' => 'Количество предмета должно быть не менее 1 шт.',
        ],
    ]);

    it('returns not found when the inventory does not exist', function () {
        $response = $this->be($this->user)->postJson(route('inventory-place', $this->inventory->id + 1000), [
            'cell_id' => $this->cell->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Предмет с переданным id не найден',
        ]);
    });

    it('returns not found when the cell does not exist', function () {
        $response = $this->be($this->user)->postJson(route('inventory-place', $this->inventory->id), [
            'cell_id' => $this->cell->id + 1000,
            'quantity' => 5,
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Ячейка с переданным id не найдена',
        ]);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->postJson(route('inventory-place', $this->inventory->id), [
            'cell_id' => $this->cell->id,
            'quantity' => 5,
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});
