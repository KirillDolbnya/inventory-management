<?php

namespace App\UseCases\Warehouse\Delete;

use App\Exceptions\WarehouseHasPlacementsException;
use App\Exceptions\WarehouseNotFoundException;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Facades\DB;

class DeleteWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository
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

            $this->warehouseRepository->delete($warehouse);
        });
    }
}
