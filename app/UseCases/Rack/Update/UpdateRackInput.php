<?php

namespace App\UseCases\Rack\Update;

readonly class UpdateRackInput
{
    public function __construct(
        public int $warehouseId,
        public int $rackId,
        public ?string $code,
        public ?int $levelsCount,
        public ?int $cellsPerLevel,
    ) {}
}
