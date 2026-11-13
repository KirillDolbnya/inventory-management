<?php

namespace App\UseCases\Warehouse\QueryLayout;

readonly class QueryDetailWarehouseLayoutInput
{
    public function __construct(
        public int $warehouseId
    ) {}
}
