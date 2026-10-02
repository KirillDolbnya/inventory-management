<?php

namespace App\UseCases\Warehouse\Delete;

readonly class DeleteWarehouseInput
{
    public function __construct(
        public int $id
    ) {}
}
