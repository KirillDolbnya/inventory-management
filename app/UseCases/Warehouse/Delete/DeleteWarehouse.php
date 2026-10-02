<?php

namespace App\UseCases\Warehouse\Delete;

use App\Exceptions\WarehouseNotFoundException;
use App\Repositories\WarehouseRepository;

class DeleteWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository
    ) {}

    /**
     * @throws WarehouseNotFoundException
     */
    public function __invoke(DeleteWarehouseInput $input): void
    {
        $warehouse = $this->warehouseRepository->getById($input->id);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        $this->warehouseRepository->delete($warehouse);
    }
}
