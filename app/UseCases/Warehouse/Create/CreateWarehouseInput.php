<?php

namespace App\UseCases\Warehouse\Create;

readonly class CreateWarehouseInput
{
    public function __construct(
        public string $name,
        public string $fiasId,
    ) {}
}
