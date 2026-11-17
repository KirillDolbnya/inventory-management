<?php

namespace App\UseCases\Inventory\Transfer;

readonly class TransferInventoryPlacementOutput
{
    public function __construct(
        public int $placementId,
        public int $cellId,
        public int $quantity
    ) {}
}
