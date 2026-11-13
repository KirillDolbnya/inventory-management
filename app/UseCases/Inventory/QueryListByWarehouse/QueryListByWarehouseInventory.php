<?php

namespace App\UseCases\Inventory\QueryListByWarehouse;

use App\Exceptions\WarehouseNotFoundException;
use App\Models\Inventory;
use App\Models\InventoryPlacement;
use App\Repositories\InventoryRepository;
use App\Repositories\WarehouseRepository;

class QueryListByWarehouseInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly WarehouseRepository $warehouseRepository,
    ) {}

    /**
     * @throws WarehouseNotFoundException
     */
    public function __invoke(QueryListByWarehouseInventoryInput $input): QueryListByWarehouseInventoryItemsOutput
    {
        $warehouse = $this->warehouseRepository->getById($input->warehouseId);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        $inventories = $this->inventoryRepository->getAllByWarehouse($input->warehouseId);

        $inventoriesData = $inventories->map(function (Inventory $inventory) {
            $quantityInWarehouse = (int) $inventory->inventoryPlacements->sum('quantity');

            $placementsData = $inventory->inventoryPlacements->map(function (InventoryPlacement $placement) {
                $cell = $placement->cell;
                $rack = $cell->rack;

                return new QueryListByWarehouseInventoryPlacementOutput(
                    $placement->id,
                    $placement->quantity,
                    new QueryListByWarehouseInventoryLocationOutput(
                        $rack->id,
                        $rack->code,

                        $cell->id,
                        $cell->number,
                        $cell->level,
                        $cell->position,
                    ),
                );
            })->values()->all();

            return new QueryListByWarehouseInventoryItemOutput(
                $inventory->id,
                $inventory->name,
                $inventory->description,
                $inventory->quantity,
                $quantityInWarehouse,
                $placementsData,
            );
        })->values()->all();

        return new QueryListByWarehouseInventoryItemsOutput($inventoriesData);
    }
}
