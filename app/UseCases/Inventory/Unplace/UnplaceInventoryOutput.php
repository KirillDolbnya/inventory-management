<?php

namespace App\UseCases\Inventory\Unplace;

readonly class UnplaceInventoryOutput
{
    public function __construct(
        public int $inventoryId,
        public int $removedQuantity,
        public int $placementQuantity,
        public int $placedQuantity,
        public int $unplacedQuantity,
    ) {}
}
