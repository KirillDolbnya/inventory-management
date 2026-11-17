<?php

namespace App\UseCases\Inventory\QueryListByCell;

use App\Exceptions\CellNotFoundException;
use App\Models\Inventory;
use App\Repositories\CellRepository;
use App\Repositories\InventoryRepository;

class QueryListByCellInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly CellRepository $cellRepository
    ) {}

    /**
     * @throws CellNotFoundException
     */
    public function __invoke(QueryListByCellInventoryInput $input): QueryListByCellInventoryItemsOutput
    {
        if (! $this->cellRepository->cellExistsById($input->cellId)) {
            throw new CellNotFoundException;
        }

        $inventories = $this->inventoryRepository->getAllByCell($input->cellId);

        $inventoriesData = $inventories->map(function (Inventory $inventory) {
            $placement = $inventory->inventoryPlacements->sole();

            return new QueryListByCellInventoryItemOutput(
                $inventory->id,
                $inventory->name,
                $inventory->description,
                $inventory->quantity,
                $placement->quantity,
                $placement->id
            );
        })->values()->all();

        return new QueryListByCellInventoryItemsOutput($inventoriesData);
    }
}
