<?php

namespace App\UseCases\Event\QueryDetail;

readonly class QueryDetailEventInventoryOutput
{
    public function __construct(
        public int $inventoryId,
        public string $name,
        public int $quantity,
    ) {}
}
