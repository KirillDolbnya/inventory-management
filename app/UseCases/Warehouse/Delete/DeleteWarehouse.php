<?php

namespace App\UseCases\Warehouse\Delete;

use App\Exceptions\WarehouseHasPlacementsException;
use App\Exceptions\WarehouseNotFoundException;
use App\Repositories\CellRepository;
use App\Repositories\RackRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Facades\DB;

class DeleteWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
        private readonly RackRepository $rackRepository,
        private readonly CellRepository $cellRepository,
    ) {}

    /**
     * @throws WarehouseNotFoundException
     * @throws WarehouseHasPlacementsException
     * @throws \Throwable
     */
    public function __invoke(DeleteWarehouseInput $input): void
    {
        $warehouse = $this->warehouseRepository->getById($input->id);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        DB::transaction(function () use ($input) {
            $warehouse = $this->warehouseRepository->getByIdForUpdate($input->id);

            if ($warehouse === null) {
                throw new WarehouseNotFoundException;
            }

            if ($this->warehouseRepository->hasPlacements($warehouse->id)) {
                throw new WarehouseHasPlacementsException;
            }

            $racks = $this->rackRepository->getAllByWarehouseId($warehouse->id);

            if ($racks->isNotEmpty()) {
                $rackIds = $racks->pluck('id')->toArray();
                $cells = $racks->pluck('cells');
                $cellIds = $cells->collapse()->pluck('id')->toArray();

                $this->rackRepository->deleteRacks($rackIds);
                $this->cellRepository->deleteCells($cellIds);
            }

            $this->warehouseRepository->delete($warehouse);
        });
    }
}
