<?php

use App\Models\Inventory;
use App\Models\InventoryPlacement;
use App\Models\User;
use App\Repositories\InventoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

describe('Inventory unplacement', function () {
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

        $this->inventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => 5,
        ]);
    });

    it('removes an entire inventory placement', function () {
        $response = $this->be($this->user)->postJson(route('inventory-placements-unplace', $this->inventoryPlacement->id), [
            'quantity' => 5,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'removed_quantity' => 5,
            'placement_quantity' => 0,
            'placed_quantity' => 0,
            'unplaced_quantity' => 10,
        ]);

        assertDatabaseMissing('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
        ]);
    });

    it('removes part of an inventory placement', function () {
        $anotherInventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => 3,
        ]);

        $response = $this->be($this->user)->postJson(route('inventory-placements-unplace', $this->inventoryPlacement->id), [
            'quantity' => 2,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'removed_quantity' => 2,
            'placement_quantity' => 3,
            'placed_quantity' => 6,
            'unplaced_quantity' => 4,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => 3,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $anotherInventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => $anotherInventoryPlacement->quantity,
        ]);
    });

    it('removes one of multiple inventory placements', function () {
        $anotherInventoryPlacement = InventoryPlacement::factory()->create([
            'inventory_id' => $this->inventory->id,
            'quantity' => 3,
        ]);

        $response = $this->be($this->user)->postJson(route('inventory-placements-unplace', $this->inventoryPlacement->id), [
            'quantity' => 5,
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'inventory_id' => $this->inventory->id,
            'removed_quantity' => 5,
            'placement_quantity' => 0,
            'placed_quantity' => 3,
            'unplaced_quantity' => 7,
        ]);

        assertDatabaseMissing('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
        ]);

        assertDatabaseHas('inventory_placements', [
            'id' => $anotherInventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => $anotherInventoryPlacement->quantity,
        ]);
    });

    it('rejects invalid unplacement data', function (array $payload, string $errorKey, string $expectedMessage) {
        $response = $this->be($this->user)->postJson(route('inventory-placements-unplace', $this->inventoryPlacement->id), $payload);

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
        'quantity_is_required' => [
            'payload' => [],
            'errorKey' => 'quantity',
            'expectedMessage' => 'Количество предмета обязательно для заполнения.',
        ],
        'quantity_must_be_an_integer' => [
            'payload' => ['quantity' => 'не_число'],
            'errorKey' => 'quantity',
            'expectedMessage' => 'Количество предмета должно быть целым числом.',
        ],
        'quantity_cannot_be_less_than_one' => [
            'payload' => ['quantity' => 0],
            'errorKey' => 'quantity',
            'expectedMessage' => 'Количество предмета должно быть не менее 1 шт.',
        ],
    ]);

    it('rejects a quantity greater than the placement quantity', function () {
        $response = $this->be($this->user)->postJson(route('inventory-placements-unplace', $this->inventoryPlacement->id), [
            'quantity' => 6,
        ]);

        $response->assertStatus(422);

        $response->assertJson([
            'message' => 'Запрашиваемое количество 6 превышает фактический остаток в данном месте хранения. Доступно для удаления: 5 шт.',
            'errors' => [
                'quantity' => ['Запрашиваемое количество 6 превышает фактический остаток в данном месте хранения. Доступно для удаления: 5 шт.'],
            ],
        ]);

        assertDatabaseCount('inventory_placements', 1);

        assertDatabaseHas('inventory_placements', [
            'id' => $this->inventoryPlacement->id,
            'inventory_id' => $this->inventory->id,
            'quantity' => $this->inventoryPlacement->quantity,
        ]);
    });

    it('returns not found when the inventory placement does not exist', function () {
        $response = $this->be($this->user)->postJson(route('inventory-placements-unplace', $this->inventoryPlacement->id + 1000), [
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

        $response = $this->be($this->user)->postJson(route('inventory-placements-unplace', $this->inventoryPlacement->id), [
            'quantity' => 1,
        ]);

        $response->assertStatus(404);

        $response->assertJson([
            'message' => 'Предмет с переданным id не найден',
        ]);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->postJson(route('inventory-placements-unplace', $this->inventoryPlacement->id), [
            'quantity' => 1,
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});
