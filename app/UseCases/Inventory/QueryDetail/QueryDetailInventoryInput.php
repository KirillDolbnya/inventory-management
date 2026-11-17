<?php

namespace App\UseCases\Inventory\QueryDetail;

readonly class QueryDetailInventoryInput
{
    public function __construct(
        public int $id
    ) {}
}
