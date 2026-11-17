<?php

namespace App\UseCases\Warehouse\Layout;

use Illuminate\Http\UploadedFile;

readonly class SaveWarehouseLayoutInput
{
    /**
     * @param  list<SaveWarehouseLayoutRackInput>  $racks
     * @param  array{outline: list<array{x: float, z: float}>}  $walls
     */
    public function __construct(
        public int $warehouseId,
        public UploadedFile $fileModel,
        public array $racks,
        public array $walls,
    ) {}
}
