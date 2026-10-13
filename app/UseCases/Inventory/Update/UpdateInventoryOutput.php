<?php

namespace App\UseCases\Inventory\Update;

readonly class UpdateInventoryOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $quantity,
    ) {}
}
