<?php

namespace App\Repositories;

use App\Data\Addresses\ResolvedAddress;
use App\Models\Warehouse;
use Illuminate\Support\Collection;

class WarehouseRepository
{
    public function create(string $name, string $address, string $fiasId, float $latitude, float $longitude): Warehouse
    {
        return Warehouse::create([
            'name' => $name,
            'address' => $address,
            'fias_id' => $fiasId,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ]);
    }

    public function update(Warehouse $warehouse, ?string $nameChange, ?ResolvedAddress $addressChange): Warehouse
    {
        $updateData = [];

        if ($nameChange !== null) {
            $updateData['name'] = $nameChange;
        }

        if ($addressChange !== null) {
            $updateData['address'] = $addressChange->getAddress();
            $updateData['fias_id'] = $addressChange->getFiasId();
            $updateData['latitude'] = $addressChange->getLatitude();
            $updateData['longitude'] = $addressChange->getLongitude();
        }

        if (empty($updateData)) {
            return $warehouse;
        }

        $warehouse->update($updateData);

        return $warehouse;
    }

    public function delete(Warehouse $warehouse): void
    {
        $warehouse->delete();
    }

    /**
     * @return Collection<int, Warehouse>
     */
    public function getAll(): Collection
    {
        return Warehouse::query()->orderBy('id', 'asc')->get();
    }

    public function getById(int $id): ?Warehouse
    {
        return Warehouse::query()->find($id);
    }

    public function getByIdForUpdate(int $id): ?Warehouse
    {
        return Warehouse::query()->lockForUpdate()->find($id);
    }

    public function hasPlacements(int $id): bool
    {
        return Warehouse::query()->where('id', $id)->whereHas('racks.cells.inventoryPlacements')->exists();
    }

    /**
     * @param  array{outline: list<array{x: float|int, z: float|int}>}  $walls
     */
    public function saveModelFileWithJson(Warehouse $warehouse, string $modelPath, array $walls): Warehouse
    {
        $warehouse->update([
            'walls' => $walls,
            'model_path' => $modelPath,
        ]);

        return $warehouse;
    }

    public function existsByName(string $name, ?int $id = null): bool
    {
        return Warehouse::query()->where('name', $name)->when($id !== null, fn ($query) => $query->whereKeyNot($id))->withTrashed()->exists();
    }

    public function existsByFiasId(string $fiasId, ?int $id = null): bool
    {
        return Warehouse::query()->where('fias_id', $fiasId)->when($id !== null, fn ($query) => $query->whereKeyNot($id))->withTrashed()->exists();
    }
}
