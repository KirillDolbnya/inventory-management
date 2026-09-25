<?php

namespace App\Repositories;

use App\Models\Warehouse;

class WarehouseRepository
{
    public function create(string $name, string $address, string $fiasId, string $latitude, string $longitude): Warehouse
    {
        return Warehouse::create([
            'name' => $name,
            'address' => $address,
            'fias_id' => $fiasId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    public function existsByName(string $name): bool
    {
        return Warehouse::query()->where('name', $name)->exists();
    }

    public function existsByFiasId(string $fiasId): bool
    {
        return Warehouse::query()->where('fias_id', $fiasId)->exists();
    }
}
