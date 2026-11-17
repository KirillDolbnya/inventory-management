<?php

namespace App\UseCases\Inventory\Create;

readonly class CreateInventoryOutput
{
    public function __construct(
        public int $id,
        public string $name,
        public ?string $description,
        public int $quantity,
    ) {}
}
