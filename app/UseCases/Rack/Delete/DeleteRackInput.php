<?php

namespace App\UseCases\Rack\Delete;

readonly class DeleteRackInput
{
    public function __construct(
        public int $warehouseId,
        public int $rackId,
    ) {}
}
