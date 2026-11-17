<?php

namespace App\UseCases\Warehouse\Layout;

readonly class SaveWarehouseLayoutRackInput
{
    public function __construct(
        public int $rackId,
        public float $positionX,
        public float $positionY,
        public float $positionZ,
        public float $rotationYDeg,
        public float $width,
        public float $depth,
        public float $height,
    ) {}
}
