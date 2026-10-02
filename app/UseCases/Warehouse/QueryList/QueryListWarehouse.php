<?php

namespace App\UseCases\Warehouse\QueryList;

use App\Models\Warehouse;
use App\Repositories\WarehouseRepository;

class QueryListWarehouse
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository
    ) {}

    public function __invoke(): QueryListWarehouseItemsOutput
    {
        $warehouses = $this->warehouseRepository->getAll();

        $items = $warehouses->map(fn (Warehouse $warehouse) => new QueryListWarehouseItemOutput(
            $warehouse->id,
            $warehouse->name,
            $warehouse->address,
            $warehouse->latitude,
            $warehouse->longitude,
        ))->all();

        return new QueryListWarehouseItemsOutput($items);
    }
}
