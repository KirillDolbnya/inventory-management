<?php

namespace App\UseCases\Inventory\Unplace;

readonly class UnplaceInventoryInput
{
    public function __construct(
        public int $inventoryPlacementId,
        public int $quantity
    ) {}
}
