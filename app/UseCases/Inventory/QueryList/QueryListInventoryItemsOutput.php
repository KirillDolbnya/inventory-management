<?php

namespace App\UseCases\Inventory\QueryList;

readonly class QueryListInventoryItemsOutput
{
    /**
     * @param  list<QueryListInventoryItemOutput>  $items
     */
    public function __construct(
        public array $items
    ) {}
}
