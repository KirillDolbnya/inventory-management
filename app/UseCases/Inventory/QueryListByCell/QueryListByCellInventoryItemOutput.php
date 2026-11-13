<?php

namespace App\UseCases\Inventory\QueryListByCell;

readonly class QueryListByCellInventoryItemOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $quantity,
        public int $quantityInCell,
        public int $placementId,
    ) {}
}
