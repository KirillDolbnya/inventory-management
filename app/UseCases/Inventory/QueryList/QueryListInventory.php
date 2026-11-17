<?php

namespace App\UseCases\Inventory\QueryList;

use App\Models\Inventory;
use App\Models\InventoryPlacement;
use App\Repositories\InventoryRepository;

class QueryListInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository
    ) {}

    public function __invoke(): QueryListInventoryItemsOutput
    {
        $inventories = $this->inventoryRepository->getAllWithPlacements();

        $inventoriesData = $inventories->map(function (Inventory $inventory) {
            $placedQuantity = (int) $inventory->inventoryPlacements->sum('quantity');
            $unplacedQuantity = $inventory->quantity - $placedQuantity;

            $placementsData = $inventory->inventoryPlacements->map(function (InventoryPlacement $placement) {
                $cell = $placement->cell;
                $rack = $cell->rack;
                $warehouse = $rack->warehouse;

                return new QueryListInventoryPlacementOutput(
                    $placement->id,
                    $placement->quantity,
                    new QueryListInventoryLocationOutput(
                        $warehouse->id,
                        $warehouse->name,

                        $rack->id,
                        $rack->code,

                        $cell->id,
                        $cell->number,
                        $cell->level,
                        $cell->position,
                    ),
                );
            })->values()->all();

            return new QueryListInventoryItemOutput(
                $inventory->id,
                $inventory->name,
                $inventory->description,
                $inventory->quantity,
                $placedQuantity,
                $unplacedQuantity,
                $placementsData,
            );
        })->values()->all();

        return new QueryListInventoryItemsOutput($inventoriesData);
    }
}
