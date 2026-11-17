<?php

namespace App\UseCases\Inventory\QueryListByWarehouse;

readonly class QueryListByWarehouseInventoryInput
{
    public function __construct(
        public int $warehouseId,
    ) {}
}
