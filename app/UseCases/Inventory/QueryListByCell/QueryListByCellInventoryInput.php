<?php

namespace App\UseCases\Inventory\QueryListByCell;

readonly class QueryListByCellInventoryInput
{
    public function __construct(
        public int $cellId
    ) {}
}
