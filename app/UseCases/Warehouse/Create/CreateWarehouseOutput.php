<?php

namespace App\UseCases\Warehouse\Create;

readonly class CreateWarehouseOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public string $address,
        public float $latitude,
        public float $longitude,
    ) {}
}
