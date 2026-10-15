<?php

namespace App\UseCases\Inventory\Transfer;

readonly class TransferInventoryOutput
{
    public function __construct(
        public int $inventoryId,
        public int $transferredQuantity,
        public ?TransferInventoryPlacementOutput $sourcePlacement,
        public TransferInventoryPlacementOutput $targetPlacement,
        public int $placedQuantity,
        public int $unplacedQuantity,
    ) {}
}
