<?php

namespace App\UseCases\Warehouse\UpdateLayout;

readonly class UpdateLayoutOutput
{
    /**
     * @param  array{outline: list<array{x: float|int, z: float|int}>}|null  $walls
     * @param  array{
     *     url: string,
     *     format: string,
     *     unit: string,
     *     origin: array{x: float, y: float, z: float}|string
     * }|null  $file
     * @param  list<UpdateLayoutRackOutput>  $racks
     */
    public function __construct(
        public int $warehouseId,
        public ?array $walls,
        public ?array $file,
        public ?array $racks
    ) {}
}
