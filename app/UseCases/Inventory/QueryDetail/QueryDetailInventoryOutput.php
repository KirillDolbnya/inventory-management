<?php

namespace App\UseCases\Inventory\QueryDetail;

readonly class QueryDetailInventoryOutput
{
    /**
     * @param  list<QueryDetailInventoryPlacementOutput>  $placements
     */
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $quantity,
        public array $placements,
    ) {}
}
