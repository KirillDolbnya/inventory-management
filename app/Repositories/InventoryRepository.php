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

    public function existsByName(string $name): bool
    {
        return Inventory::query()->where('name', $name)->exists();
    }
}
