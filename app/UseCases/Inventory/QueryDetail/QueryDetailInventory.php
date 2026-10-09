<?php

namespace App\UseCases\Inventory\QueryDetail;

use App\Exceptions\InventoryNotFoundException;
use App\Models\InventoryPlacement;
use App\Repositories\InventoryRepository;

class QueryDetailInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
    ) {}

    /**
     * @throws InventoryNotFoundException
     */
    public function __invoke(QueryDetailInventoryInput $input): QueryDetailInventoryOutput
    {
        $inventory = $this->inventoryRepository->getByIdWithPlacements($input->id);

        if ($inventory === null) {
            throw new InventoryNotFoundException;
        }

        $inventoryPlacements = $inventory->inventoryPlacements->map(fn (InventoryPlacement $placement) => new QueryDetailInventoryPlacementOutput(
            $placement->id,
            $placement->cell_id,
            $placement->quantity
        ))->values()->all();

        return new QueryDetailInventoryOutput($inventory->id, $inventory->name, $inventory->description, $inventory->quantity, $inventoryPlacements);
    }
}
