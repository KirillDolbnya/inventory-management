<?php

namespace App\UseCases\Inventory\Place;

use App\Exceptions\CellNotFoundException;
use App\Exceptions\InventoryNotFoundException;
use App\Exceptions\InventoryPlacementQuantityExceededException;
use App\Repositories\CellRepository;
use App\Repositories\InventoryRepository;
use Illuminate\Support\Facades\DB;

class PlaceInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly CellRepository $cellRepository
    ) {}

    /**
     * @throws InventoryNotFoundException
     * @throws CellNotFoundException
     * @throws InventoryPlacementQuantityExceededException
     * @throws \Throwable
     */
    public function __invoke(PlaceInventoryInput $input): PlaceInventoryOutput
    {
        return DB::transaction(function () use ($input) {
            $inventory = $this->inventoryRepository->getByIdForUpdate($input->inventoryId);

            if ($inventory === null) {
                throw new InventoryNotFoundException;
            }

            $cell = $this->cellRepository->getById($input->cellId);

            if ($cell === null) {
                throw new CellNotFoundException;
            }

            $placedQuantity = $inventory->inventoryPlacements->sum('quantity');
            $remainderQuantity = $inventory->quantity - $placedQuantity;

            if ($input->quantity > $remainderQuantity) {
                throw new InventoryPlacementQuantityExceededException('Запрашиваемое количество '.$input->quantity.' превышает свободный остаток предмета. Доступно для размещения: '.$remainderQuantity.' шт.');
            }

            $placement = $this->inventoryRepository->place($inventory->id, $cell->id, $input->quantity);
            $remainderQuantity = $remainderQuantity - $input->quantity;
            $placedQuantity = $placedQuantity + $input->quantity;

            return new PlaceInventoryOutput(
                $placement->id,
                $placement->inventory_id,
                $placement->cell_id,
                $inventory->quantity,
                $placement->quantity,
                $placedQuantity,
                $remainderQuantity
            );
        });
    }
}
