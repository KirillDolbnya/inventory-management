<?php

namespace App\UseCases\Warehouse\DeleteLayout;

readonly class DeleteWarehouseLayoutInput
{
    public function __construct(
        public int $warehouseId,
    ) {}
}
