<?php

namespace App\UseCases\Warehouse\QueryList;

readonly class QueryListWarehouseItemOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public string $address,
        public float $latitude,
        public float $longitude,
    ) {}
}
