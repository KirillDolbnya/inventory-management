<?php

namespace App\UseCases\Rack\Delete;

use App\Exceptions\RackHasPlacementsException;
use App\Exceptions\RackNotFoundException;
use App\Exceptions\WarehouseNotFoundException;
use App\Repositories\CellRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\RackRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Facades\DB;

class DeleteRack
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
        private readonly RackRepository $rackRepository,
        private readonly CellRepository $cellRepository,
        private readonly InventoryRepository $inventoryRepository,
    ) {}

    /**
     * @throws WarehouseNotFoundException
     * @throws RackNotFoundException
     * @throws \Throwable
     */
    public function __invoke(DeleteRackInput $input): void
    {
        $warehouse = $this->warehouseRepository->getById($input->warehouseId);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        $rack = $this->rackRepository->getByRackIdWithWarehouseId($warehouse->id, $input->rackId);

        if ($rack === null) {
            throw new RackNotFoundException;
        }

        DB::transaction(function () use ($rack, $warehouse): void {
            $warehouse = $this->warehouseRepository->getByIdForUpdate($warehouse->id);

            if ($warehouse === null) {
                throw new WarehouseNotFoundException;
            }

            $rack = $this->rackRepository->getByRackIdWithWarehouseId($warehouse->id, $rack->id);

            if ($rack === null) {
                throw new RackNotFoundException;
            }

            $cells = $this->cellRepository->getByRackId($rack->id);
            $cellIds = $cells->pluck('id')->all();

            $placements = $this->inventoryRepository->getPlacementsByCellIds($cellIds);

            if ($placements->isNotEmpty()) {
                throw new RackHasPlacementsException;
            }

            $this->rackRepository->delete($rack);
            $this->cellRepository->deleteCellByIds($cellIds);
        });
    }
}
