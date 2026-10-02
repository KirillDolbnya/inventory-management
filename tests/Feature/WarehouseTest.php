<?php

use App\Models\User;
use App\Repositories\WarehouseRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\assertDatabaseCount;

uses(RefreshDatabase::class);

describe('Warehouse creation', function () {
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

    it('creates a warehouse', function () {
        assertDatabaseCount('warehouses', 0);

        $response = $this->be($this->user)->postJson(route('warehouses-create'), [
            'name' => 'Склад 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
        ]);

        $response->assertStatus(201);

        $response->assertJson([
            'id' => 1,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => 1,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);

        assertDatabaseCount('warehouses', 1);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->postJson(route('warehouses-create'), [
            'name' => 'Склад 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        assertDatabaseCount('warehouses', 0);
    });

    it('rejects request missing required fields', function () {
        $response = $this->be($this->user)->postJson(route('warehouses-create'), [
            'name' => '',
            'fias_id' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name' => 'Пожалуйста, введите название.',
                'fias_id' => 'Пожалуйста, укажите ФИАС ID адреса.',
            ]);

        assertDatabaseCount('warehouses', 0);
    });

    it('rejects request with invalid data type name', function () {
        $response = $this->be($this->user)->postJson(route('warehouses-create'), [
            'name' => 123,
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Название должно быть строкой.']);

        assertDatabaseCount('warehouses', 0);
    });

    it('rejects request with invalid data type fias id', function () {
        $response = $this->be($this->user)->postJson(route('warehouses-create'), [
            'name' => 'Склад 1',
            'fias_id' => 123,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fias_id' => 'ФИАС ID адреса должен быть строкой.']);

        assertDatabaseCount('warehouses', 0);
    });

    it('rejects request with invalid data format fias id', function () {
        $response = $this->be($this->user)->postJson(route('warehouses-create'), [
            'name' => 'Склад 1',
            'fias_id' => 'asdsdadasdsdasds-asdasd',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fias_id' => 'ФИАС ID адреса имеет неверный формат UUID.']);

        assertDatabaseCount('warehouses', 0);
    });

    it('rejects request with duplicate warehouse name', function () {
        $warehouseRepository = new WarehouseRepository;

        $warehouseRepository->create('Склад 1', 'г. Москва, Ленинские горы, д. 1', 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e', 55.702936, 37.530768);

        assertDatabaseCount('warehouses', 1);

        $response = $this->be($this->user)->postJson(route('warehouses-create'), [
            'name' => 'Склад 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Название склада уже существует']);

        assertDatabaseCount('warehouses', 1);
    });

    it('rejects request with duplicate warehouse fias id', function () {
        $warehouseRepository = new WarehouseRepository;

        $warehouseRepository->create('Склад 1', 'г. Москва, Ленинские горы, д. 1', '8ed1481e-1f9e-4340-9774-325db197bf5d', 55.702936, 37.530768);

        assertDatabaseCount('warehouses', 1);

        $response = $this->be($this->user)->postJson(route('warehouses-create'), [
            'name' => 'Склад 2',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fias_id' => 'Указанный ФИАС ID занят другим складом']);

        assertDatabaseCount('warehouses', 1);
    });

    it('rejects an unknown FIAS ID', function () {
        assertDatabaseCount('warehouses', 0);

        $response = $this->be($this->user)->postJson(route('warehouses-create'), [
            'name' => 'Склад 2',
            'fias_id' => 'cd2a3b4e-5f6a-7b8c-9d0e-1f2a3b4c5d6e',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fias_id' => 'Заданный ФИАС ID не существует']);

        assertDatabaseCount('warehouses', 0);
    });
});

describe('Warehouse update', function () {
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
    });

    it('updates a warehouse name and address', function () {
        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'name' => 'Склад 2',
            'fias_id' => 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e',
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->warehouse->id,
            'name' => 'Склад 2',
            'address' => 'г. Москва, ул. Петровка, д. 17',
            'latitude' => 55.7646600,
            'longitude' => 37.6162100,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 2',
            'address' => 'г. Москва, ул. Петровка, д. 17',
            'fias_id' => 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e',
            'latitude' => 55.7646600,
            'longitude' => 37.6162100,
        ]);
    });

    it('does not update a warehouse when data is unchanged', function () {
        $originalUpdatedAt = $this->warehouse->updated_at->toIso8601String();

        $this->travel(5)->minute();

        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'name' => 'Склад 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);

        $this->warehouse->refresh();

        expect($this->warehouse->updated_at->toIso8601String())->toBe($originalUpdatedAt);
    });

    it('updates only the warehouse name', function () {
        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'name' => 'Склад 2',
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->warehouse->id,
            'name' => 'Склад 2',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 2',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });

    it('updates warehouse address data when the FIAS ID changes', function () {
        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'fias_id' => 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e',
        ]);

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, ул. Петровка, д. 17',
            'latitude' => 55.7646600,
            'longitude' => 37.6162100,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, ул. Петровка, д. 17',
            'fias_id' => 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e',
            'latitude' => 55.7646600,
            'longitude' => 37.6162100,
        ]);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'name' => 'Склад 2',
            'fias_id' => 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e',
        ]);

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });

    it('rejects request missing required fields', function () {
        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name' => 'Передайте хотя бы один параметр для обновления (название или ФИАС ID).',
                'fias_id' => 'Передайте хотя бы один параметр для обновления (название или ФИАС ID).',
            ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });

    it('rejects a name with an invalid data type', function () {
        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'name' => 123,
            'fias_id' => 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Название должно быть строкой.']);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });

    it('rejects a FIAS ID with an invalid data type', function () {
        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'name' => 'Склад 2',
            'fias_id' => 123,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fias_id' => 'ФИАС ID адреса должен быть строкой.']);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });

    it('rejects an update for a missing warehouse', function () {
        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id + 1000]), [
            'name' => 'Склад 2',
            'fias_id' => 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Склад с передаваемым id не найден',
            ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });

    it('rejects a FIAS ID with an invalid UUID format', function () {
        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'name' => 'Склад 2',
            'fias_id' => 'asdsdadasdsdasds-asdasd',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fias_id' => 'ФИАС ID адреса имеет неверный формат UUID.']);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });

    it('rejects a name used by another warehouse', function () {
        $warehouseRepository = new WarehouseRepository;

        $warehouseRepository->create('Склад 2', 'г. Москва, ул. Петровка, д. 17', 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e', 55.7646600, 37.6162100);

        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'name' => 'Склад 2',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name' => 'Название склада уже существует']);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });

    it('rejects a FIAS ID used by another warehouse', function () {
        $warehouseRepository = new WarehouseRepository;

        $warehouseRepository->create('Склад 2', 'г. Москва, ул. Петровка, д. 17', 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e', 55.7646600, 37.6162100);

        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'fias_id' => 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'fias_id' => 'Указанный ФИАС ID занят другим складом',
            ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });

    it('rejects an unknown FIAS ID', function () {
        $response = $this->be($this->user)->patchJson(route('warehouses-update', ['warehouseId' => $this->warehouse->id]), [
            'name' => 'Склад 2',
            'fias_id' => 'cd2a3b4e-5f6a-7b8c-9d0e-1f2a3b4c5d6e',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fias_id' => 'Заданный ФИАС ID не существует']);

        $this->assertDatabaseHas('warehouses', [
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'fias_id' => '8ed1481e-1f9e-4340-9774-325db197bf5d',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });
});

describe('Warehouse show', function () {
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
    });

    it('shows a warehouse', function () {
        $response = $this->be($this->user)->getJson(route('warehouses-show', ['warehouseId' => $this->warehouse->id]));

        $response->assertStatus(200);

        $response->assertJson([
            'id' => $this->warehouse->id,
            'name' => 'Склад 1',
            'address' => 'г. Москва, Ленинские горы, д. 1',
            'latitude' => 55.702936,
            'longitude' => 37.530768,
        ]);
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->getJson(route('warehouses-show', ['warehouseId' => $this->warehouse->id]));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });

    it('returns 404 for a missing warehouse', function () {
        $response = $this->be($this->user)->getJson(route('warehouses-show', ['warehouseId' => $this->warehouse->id + 1000]));

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Склад с передаваемым id не найден',
            ]);
    });

    it('returns 404 when the warehouse ID is not numeric', function () {
        $response = $this->be($this->user)->getJson(route('warehouses-show', ['warehouseId' => 'text']));

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Запрашиваемый ресурс или маршрут не найден.',
            ]);
    });
});

describe('Warehouse index', function () {
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

    it('returns a list of warehouses', function () {
        $warehouseRepository = new WarehouseRepository;

        $this->warehouseOne = $warehouseRepository->create('Склад 1', 'г. Москва, Ленинские горы, д. 1', '8ed1481e-1f9e-4340-9774-325db197bf5d', 55.702936, 37.530768);
        $this->warehouseTwo = $warehouseRepository->create('Склад 2', 'г. Москва, ул. Петровка, д. 17', 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e', 55.7646600, 37.6162100);

        $response = $this->be($this->user)->getJson(route('warehouses-index'));

        $response->assertStatus(200);

        $response->assertJsonCount(2, 'data');

        $response->assertJson([
            'data' => [
                [
                    'id' => $this->warehouseOne->id,
                    'name' => 'Склад 1',
                    'address' => 'г. Москва, Ленинские горы, д. 1',
                    'latitude' => 55.702936,
                    'longitude' => 37.530768,
                ],
                [
                    'id' => $this->warehouseTwo->id,
                    'name' => 'Склад 2',
                    'address' => 'г. Москва, ул. Петровка, д. 17',
                    'latitude' => 55.764660,
                    'longitude' => 37.616210,
                ],
            ],
        ]);
    });

    it('returns an empty list when no warehouses exist', function () {
        $response = $this->be($this->user)->getJson(route('warehouses-index'));

        $response->assertStatus(200);

        $response->assertJsonCount(0, 'data');
    });

    it('rejects an unauthenticated request', function () {
        $response = $this->getJson(route('warehouses-index'));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    });
});

describe('Warehouse delete', function () {
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
    });

    it('deletes a warehouse', function () {
        assertDatabaseCount('warehouses', 1);

        $response = $this->be($this->user)->deleteJson(route('warehouses-delete', ['warehouseId' => $this->warehouse->id]));

        $response->assertNoContent();

        assertDatabaseCount('warehouses', 0);
    });

    it('rejects an unauthenticated request', function () {
        assertDatabaseCount('warehouses', 1);

        $response = $this->deleteJson(route('warehouses-delete', ['warehouseId' => $this->warehouse->id]));

        $response->assertStatus(401);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);

        assertDatabaseCount('warehouses', 1);
    });

    it('returns 404 for a missing warehouse', function () {
        assertDatabaseCount('warehouses', 1);

        $response = $this->be($this->user)->deleteJson(route('warehouses-delete', ['warehouseId' => $this->warehouse->id + 1000]));

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Склад с передаваемым id не найден',
            ]);

        assertDatabaseCount('warehouses', 1);
    });
});
