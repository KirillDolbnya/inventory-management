<?php

namespace App\UseCases\Warehouse\QueryList;

readonly class QueryListWarehouseItemsOutput
{
    /**
     * @param  list<QueryListWarehouseItemOutput>  $items
     */
    public function __construct(
        public array $items
    ) {}
}
