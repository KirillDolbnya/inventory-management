<?php

namespace App\UseCases\Inventory\Create;

readonly class CreateInventoryInput
{
    public function __construct(
        public string $name,
        public int $quantity,
        public ?string $description = null,
        public ?int $cellId = null
    ) {}
}
