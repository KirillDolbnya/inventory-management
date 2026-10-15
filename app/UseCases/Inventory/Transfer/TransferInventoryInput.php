<?php

namespace App\UseCases\Inventory\Transfer;

readonly class TransferInventoryInput
{
    public function __construct(
        public int $placementId,
        public int $targetCellId,
        public int $quantity
    ) {}
}
