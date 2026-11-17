<?php

namespace App\UseCases\Warehouse\Layout;

readonly class SaveWarehouseLayoutRackOutput
{
    /**
     * @param  array{x: float, y: float, z: float}  $position
     * @param  array{width: float, depth: float, height: float}  $dimensions
     */
    public function __construct(
        public int $rackId,
        public string $code,
        public array $position,
        public float $rotationYDeg,
        public array $dimensions,
        public int $levelsCount,
        public int $cellsPerLevel,
    ) {}
}
