<?php

namespace App\UseCases\Inventory\Unplace;

use App\Exceptions\InsufficientPlacementQuantityException;
use App\Exceptions\InventoryNotFoundException;
use App\Exceptions\InventoryPlacementNotFoundException;
use App\Repositories\InventoryRepository;
use Illuminate\Support\Facades\DB;

class UnplaceInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
    ) {}

    /**
     * @throws InventoryNotFoundException
     * @throws InventoryPlacementNotFoundException
     * @throws InsufficientPlacementQuantityException
     * @throws \Throwable
     */
    public function __invoke(UnplaceInventoryInput $input): UnplaceInventoryOutput
    {
        $placement = $this->inventoryRepository->getPlacementById($input->inventoryPlacementId);

        if ($placement === null) {
            throw new InventoryPlacementNotFoundException;
        }

        $inventoryId = $placement->inventory_id;

        return DB::transaction(function () use ($input, $inventoryId, $placement) {
            $inventory = $this->inventoryRepository->getByIdForUpdate($inventoryId);

            if ($inventory === null) {
                throw new InventoryNotFoundException;
            }

            $placement = $this->inventoryRepository->getPlacementByIdForUpdate($placement->id, $inventoryId);

            if ($placement === null) {
                throw new InventoryPlacementNotFoundException;
            }

            $placementQuantity = $placement->quantity;
            $placedQuantity = $inventory->inventoryPlacements->sum('quantity');
            $unplacedQuantity = $inventory->quantity - $placedQuantity;

            if ($placementQuantity < $input->quantity) {
                throw new InsufficientPlacementQuantityException('Запрашиваемое количество '.$input->quantity.' превышает фактический остаток в данном месте хранения. Доступно для удаления: '.$placement->quantity.' шт.');
            }

            if ($placementQuantity > $input->quantity) {
                $remainingPlacementQuantity = $placementQuantity - $input->quantity;
                $placement = $this->inventoryRepository->updatePlacementQuantity($placement, $remainingPlacementQuantity);
            }

            if ($placementQuantity === $input->quantity) {
                $this->inventoryRepository->deletePlacement($placement);
                $placement = null;
            }

            $placementQuantity = $placement ? $placement->quantity : 0;
            $placedQuantity = $placedQuantity - $input->quantity;
            $unplacedQuantity = $unplacedQuantity + $input->quantity;

            return new UnplaceInventoryOutput($inventory->id, $input->quantity, $placementQuantity, $placedQuantity, $unplacedQuantity);
        });
    }
}
