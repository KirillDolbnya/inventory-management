<?php

namespace App\UseCases\Warehouse\Layout;

readonly class SaveWarehouseLayoutOutput
{
    /**
     * @param  array{
     *     url: string,
     *     format: string,
     *     unit: string,
     *     origin: string
     * }  $model
     * @param  list<SaveWarehouseLayoutRackOutput>  $racks
     */
    public function __construct(
        public int $warehouseId,
        public array $model,
        public array $racks,
    ) {}
}
