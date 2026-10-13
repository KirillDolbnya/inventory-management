<?php

namespace App\UseCases\Inventory\QueryListByWarehouse;

readonly class QueryListByWarehouseInventoryItemsOutput
{
    /**
     * @param  list<QueryListByWarehouseInventoryItemOutput>  $items
     */
    public function __construct(
        public array $items
    ) {}
}
