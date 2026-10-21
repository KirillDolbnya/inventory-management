<?php

namespace App\UseCases\Warehouse\QueryLayout;

readonly class QueryDetailWarehouseLayoutOutput
{
    /**
     * @param  array{
     *     url: string,
     *     format: string,
     *     unit: string,
     *     origin: string
     * }  $model
     * @param  list<QueryDetailWarehouseLayoutRackOutput>  $racks
     */
    public function __construct(
        public int $warehouseId,
        public array $model,
        public array $racks,
    ) {}
}
