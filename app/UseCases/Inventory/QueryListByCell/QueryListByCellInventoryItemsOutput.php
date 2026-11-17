<?php

namespace App\UseCases\Inventory\QueryListByCell;

readonly class QueryListByCellInventoryItemsOutput
{
    /**
     * @param  list<QueryListByCellInventoryItemOutput>  $items
     */
    public function __construct(
        public array $items
    ) {}
}
