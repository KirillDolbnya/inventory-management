<?php

namespace App\UseCases\Inventory\QueryList;

readonly class QueryListInventoryItemOutput
{
    /**
     * @param  list<QueryListInventoryPlacementOutput>  $placements
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $quantity,
        public int $placedQuantity,
        public int $unplacedQuantity,
        public array $placements,
    ) {}
}
