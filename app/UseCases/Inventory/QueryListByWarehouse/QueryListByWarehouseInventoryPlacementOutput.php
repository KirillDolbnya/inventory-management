<?php

namespace App\UseCases\Inventory\QueryListByWarehouse;

readonly class QueryListByWarehouseInventoryPlacementOutput
{
    public function __construct(
        public int $id,
        public int $quantity,
        public QueryListByWarehouseInventoryLocationOutput $location
    ) {}
}
