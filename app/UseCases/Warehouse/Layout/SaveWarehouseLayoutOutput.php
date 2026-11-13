<?php

namespace App\UseCases\Warehouse\Layout;

readonly class SaveWarehouseLayoutOutput
{
    /**
     * @param  array{outline: list<array{x: float|int, z: float|int}>}  $walls
     * @param array{
     *     url: string,
     *     format: string,
     *     unit: string,
     *     origin: string
     * } $model
     * @param  list<SaveWarehouseLayoutRackOutput>  $racks
     */
    public function __construct(
        public int $warehouseId,
        public array $walls,
        public array $model,
        public array $racks,
    ) {}
}
