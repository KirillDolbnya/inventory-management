<?php

use App\Models\Cell;
use App\Models\Inventory;
use App\Models\User;
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
