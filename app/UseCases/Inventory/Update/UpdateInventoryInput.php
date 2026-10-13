<?php

namespace App\UseCases\Inventory\Update;

readonly class UpdateInventoryInput
{
    public function __construct(
        public int $id,
        public ?string $name,
        public ?string $description,
        public bool $descriptionProvided
    ) {}
}
