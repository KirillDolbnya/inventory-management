<?php

namespace App\UseCases\Inventory\Place;

readonly class PlaceInventoryInput
{
    public function __construct(
        public int $inventoryId,
        public int $cellId,
        public int $quantity
    ) {}
}
