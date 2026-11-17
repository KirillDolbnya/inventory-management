<?php

namespace App\UseCases\Inventory\QueryList;

readonly class QueryListInventoryPlacementOutput
{
    public function __construct(
        public int $id,
        public int $quantity,
        public QueryListInventoryLocationOutput $location
    ) {}
}
