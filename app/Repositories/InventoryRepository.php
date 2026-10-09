<?php

namespace App\Repositories;

use App\Models\Inventory;
use App\Models\InventoryPlacement;

class InventoryRepository
{
    public function create(string $name, ?string $description, int $quantity): Inventory
    {
        return Inventory::query()->create([
            'name' => $name,
            'description' => $description,
            'quantity' => $quantity,
        ]);
    }

    public function assignToCell(int $cellId, Inventory $inventory): void
    {
        InventoryPlacement::query()->create([
            'inventory_id' => $inventory->id,
            'cell_id' => $cellId,
            'quantity' => $inventory->quantity,
        ]);
    }

    /**
     * @param array{
     *     name?: string,
     *     description?: string|null
     * } $changes
     */
    public function update(Inventory $inventory, array $changes): Inventory
    {
        $inventory->update($changes);

        return $inventory;
    }

    public function getById(int $id): ?Inventory
    {
        return Inventory::query()->find($id);
    }

    public function getByIdWithPlacements(int $id): ?Inventory
    {
        return Inventory::query()->with('inventoryPlacements', fn ($query) => $query->orderBy('id', 'asc'))->find($id);
    }

    public function existsByName(string $name, ?int $id = null): bool
    {
        return Inventory::query()->where('name', $name)->when($id !== null, fn ($query) => $query->whereKeyNot($id))->exists();
    }
}
