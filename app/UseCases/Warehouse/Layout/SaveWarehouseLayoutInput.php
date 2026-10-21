<?php

namespace App\UseCases\Warehouse\Layout;

use Illuminate\Http\UploadedFile;

readonly class SaveWarehouseLayoutInput
{
    /**
     * @param  list<SaveWarehouseLayoutRackInput>  $racks
     */
    public function __construct(
        public int $warehouseId,
        public UploadedFile $fileModel,
        public array $racks,
    ) {}
}
