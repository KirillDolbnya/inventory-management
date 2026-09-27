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

        $warehouseRepository->create('Склад 1', 'г. Москва, Ленинские горы, д. 1', 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e', '55.702936', '37.530768');

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

        $warehouseRepository->create('Склад 1', 'г. Москва, Ленинские горы, д. 1', '8ed1481e-1f9e-4340-9774-325db197bf5d', '55.702936', '37.530768');

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
            'fias_id' => 'b8c9d0e1-f2a3-4b5c-8d9e-0f1a2b3c4d5e',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fias_id' => 'Заданный ФИАС ID не существует']);

        assertDatabaseCount('warehouses', 0);
    });
});
