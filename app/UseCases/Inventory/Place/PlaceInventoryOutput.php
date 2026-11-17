<?php

namespace App\UseCases\Inventory\Place;

readonly class PlaceInventoryOutput
{
    public function __construct(
        public int $placementId,
        public int $inventoryId,
        public int $cellId,
        public int $inventoryQuantity,
        public int $placementQuantity,
        public int $placedQuantity,
        public int $unplacedQuantity,
    ) {}
}
