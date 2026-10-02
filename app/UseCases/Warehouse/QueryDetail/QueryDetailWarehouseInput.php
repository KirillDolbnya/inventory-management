<?php

namespace App\UseCases\Warehouse\QueryDetail;

readonly class QueryDetailWarehouseInput
{
    public function __construct(
        public int $id
    ) {}
}
