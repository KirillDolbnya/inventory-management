<?php

namespace App\UseCases\Inventory\QueryListByWarehouse;

readonly class QueryListByWarehouseInventoryItemOutput
{
    /**
     * @param  list<QueryListByWarehouseInventoryPlacementOutput>  $placements
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $quantity,
        public int $quantityInWarehouse,
        public array $placements,
    ) {}
}
