<?php

namespace App\UseCases\Rack\QueryList;

readonly class QueryListRackInput
{
    public function __construct(
        public int $warehouseId,
    ) {}
}
