<?php

namespace App\UseCases\Event\QueryDetail;

readonly class QueryDetailEventWarehouseOutput
{
    public function __construct(
        public int $warehouseId,
        public string $name
    ) {}
}
