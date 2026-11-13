<?php

namespace App\UseCases\Inventory\QueryList;

readonly class QueryListInventoryLocationOutput
{
    public function __construct(
        public int $warehouseId,
        public string $warehouseName,

        public int $rackId,
        public string $rackCode,

        public int $cellId,
        public int $cellNumber,
        public int $cellLevel,
        public int $cellPosition
    ) {}
}
