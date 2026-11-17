<?php

namespace App\UseCases\Inventory\Delete;

readonly class DeleteInventoryInput
{
    public function __construct(
        public int $id
    ) {}
}
