<?php

namespace App\UseCases\Warehouse\Update;

readonly class UpdateWarehouseInput
{
    public function __construct(
        public int $id,
        public ?string $name = null,
        public ?string $fiasId = null,
    ) {}
}
