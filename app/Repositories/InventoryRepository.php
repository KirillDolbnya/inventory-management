<?php

namespace App\Repositories;

use App\Models\Inventory;
use App\Models\InventoryPlacement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * @return Collection<int, Inventory>
     */
    public function getAllWithPlacements(): Collection
    {
        return Inventory::query()
            ->with([
                'inventoryPlacements' => fn ($query) => $query->orderBy('id', 'asc'),
                'inventoryPlacements.cell.rack.warehouse',
            ])
            ->orderBy('id', 'asc')->get();
    }

    /**
     * @return Collection<int, Inventory>
     */
    public function getAllByWarehouse(int $warehouseId): Collection
    {
        return Inventory::query()
            ->whereHas(
                'inventoryPlacements.cell.rack', fn ($query) => $query->where('warehouse_id', $warehouseId)
            )
            ->with([
                'inventoryPlacements' => fn ($query) => $query->whereHas('cell.rack', fn ($query) => $query->where('warehouse_id', $warehouseId))
                    ->orderBy('id', 'asc'),
                'inventoryPlacements.cell.rack',
            ])
            ->orderBy('id', 'asc')->get();
    }

    /**
     * @return Collection<int, Inventory>
     */
    public function getAllByCell(int $cellId): Collection
    {
        return Inventory::query()
            ->whereHas(
                'inventoryPlacements', fn ($query) => $query->where('cell_id', $cellId)
            )
            ->with([
                'inventoryPlacements' => fn ($query) => $query->where('cell_id', $cellId),
            ])
            ->orderBy('id', 'asc')->get();
    }

    public function place(int $inventoryId, int $cellId, int $quantity): InventoryPlacement
    {
        return DB::transaction(function () use ($inventoryId, $cellId, $quantity) {
            $placement = InventoryPlacement::query()->where(['inventory_id' => $inventoryId, 'cell_id' => $cellId])->lockForUpdate()->first();

            if (! $placement) {
                return InventoryPlacement::query()->create([
                    'inventory_id' => $inventoryId,
                    'cell_id' => $cellId,
                    'quantity' => $quantity,
                ]);
            }

            $placement->update([
                'quantity' => $placement->quantity + $quantity,
            ]);

            return $placement;
        });
    }

    public function updatePlacementQuantity(InventoryPlacement $placement, int $remainingQuantity): InventoryPlacement
    {
        $placement->update([
            'quantity' => $remainingQuantity,
        ]);

        return $placement;
    }

    public function getPlacementById(int $placementId): ?InventoryPlacement
    {
        return InventoryPlacement::query()->find($placementId);
    }

    public function getPlacementByIdForUpdate(int $placementId, int $inventoryId): ?InventoryPlacement
    {
        return InventoryPlacement::query()->where(['id' => $placementId, 'inventory_id' => $inventoryId])->lockForUpdate()->first();
    }

    public function deletePlacement(InventoryPlacement $placement): void
    {
        $placement->delete();
    }

    public function existsByName(string $name, ?int $id = null): bool
    {
        return Inventory::query()->where('name', $name)->when($id !== null, fn ($query) => $query->whereKeyNot($id))->exists();
    }

    public function getByIdForUpdate(int $id): ?Inventory
    {
        return Inventory::query()->where('id', $id)->lockForUpdate()->first();
    }
}
