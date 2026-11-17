<?php

namespace App\UseCases\Inventory\QueryListByWarehouse;

readonly class QueryListByWarehouseInventoryLocationOutput
{
    public function __construct(
        public int $rackId,
        public string $rackCode,

        public int $cellId,
        public int $cellNumber,
        public int $cellLevel,
        public int $cellPosition
    ) {}
}
