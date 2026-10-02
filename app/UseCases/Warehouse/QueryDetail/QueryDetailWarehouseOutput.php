<?php

namespace App\UseCases\Warehouse\QueryDetail;

readonly class QueryDetailWarehouseOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public string $address,
        public float $latitude,
        public float $longitude,
    ) {}
}
