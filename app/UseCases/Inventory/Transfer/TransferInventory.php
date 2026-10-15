<?php

namespace App\UseCases\Inventory\Transfer;

use App\Exceptions\CellNotFoundException;
use App\Exceptions\InsufficientPlacementQuantityException;
use App\Exceptions\InventoryNotFoundException;
use App\Exceptions\InventoryPlacementNotFoundException;
use App\Exceptions\SameSourceAndTargetCellException;
use App\Repositories\CellRepository;
use App\Repositories\InventoryRepository;
use Illuminate\Support\Facades\DB;

class TransferInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly CellRepository $cellRepository,
    ) {}

    /**
     * @throws InventoryPlacementNotFoundException
     * @throws InventoryNotFoundException
     * @throws CellNotFoundException
     * @throws SameSourceAndTargetCellException
     * @throws InsufficientPlacementQuantityException
     * @throws \Throwable
     */
    public function __invoke(TransferInventoryInput $input): TransferInventoryOutput
    {
        $placement = $this->inventoryRepository->getPlacementById($input->placementId);

        if ($placement === null) {
            throw new InventoryPlacementNotFoundException;
        }

        return DB::transaction(function () use ($input, $placement) {

            $inventory = $this->inventoryRepository->getByIdForUpdate($placement->inventory_id);

            if ($inventory === null) {
                throw new InventoryNotFoundException;
            }

            $placement = $this->inventoryRepository->getPlacementByIdForUpdate($input->placementId, $inventory->id);

            if ($placement === null) {
                throw new InventoryPlacementNotFoundException;
            }

            $cell = $this->cellRepository->getById($input->targetCellId);

            if ($cell === null) {
                throw new CellNotFoundException;
            }

            if ($input->targetCellId === $placement->cell_id) {
                throw new SameSourceAndTargetCellException;
            }

            $sourcePlacementQuantity = $placement->quantity;
            $placedQuantity = $inventory->inventoryPlacements->sum('quantity');
            $unplacedQuantity = $inventory->quantity - $placedQuantity;

            if ($input->quantity > $placement->quantity) {
                throw new InsufficientPlacementQuantityException('Запрашиваемое количество '.$input->quantity.' превышает фактический остаток в данном месте хранения. Доступно для перемещения: '.$placement->quantity.' шт.');
            }

            if ($input->quantity < $sourcePlacementQuantity) {
                $remainingQuantity = $placement->quantity - $input->quantity;
                $placement = $this->inventoryRepository->updatePlacementQuantity($placement, $remainingQuantity);
            }

            if ($input->quantity === $sourcePlacementQuantity) {
                $this->inventoryRepository->deletePlacement($placement);
                $placement = null;
            }

            $targetPlacement = $this->inventoryRepository->place($inventory->id, $input->targetCellId, $input->quantity);

            $sourcePlacementOutput = $placement ? new TransferInventoryPlacementOutput($placement->id, $placement->cell_id, $placement->quantity) : null;
            $targetPlacementOutput = new TransferInventoryPlacementOutput($targetPlacement->id, $targetPlacement->cell_id, $targetPlacement->quantity);

            return new TransferInventoryOutput($inventory->id, $input->quantity, $sourcePlacementOutput, $targetPlacementOutput, $placedQuantity, $unplacedQuantity);
        });
    }
}
