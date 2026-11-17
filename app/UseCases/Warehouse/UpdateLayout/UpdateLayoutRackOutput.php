<?php

namespace App\UseCases\Warehouse\UpdateLayout;

readonly class UpdateLayoutRackOutput
{
    /**
     * @param  array{x: float|null, y: float|null, z: float|null}  $position
     * @param  array{width: float|null, depth: float|null, height: float|null}  $dimensions
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
