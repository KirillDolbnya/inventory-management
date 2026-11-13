<?php

namespace App\UseCases\Inventory\QueryDetail;

readonly class QueryDetailInventoryPlacementOutput
{
    public function __construct(
        public int $id,
        public int $cellId,
        public int $quantity
    ) {}
}
