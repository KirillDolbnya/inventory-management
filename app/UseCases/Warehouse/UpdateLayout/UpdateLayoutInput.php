<?php

namespace App\UseCases\Warehouse\UpdateLayout;

use Illuminate\Http\UploadedFile;

readonly class UpdateLayoutInput
{
    /**
     * @param  list<UpdateLayoutRackInput>|null  $racks
     * @param  array{outline: list<array{x: float, z: float}>}|null  $walls
     */
    public function __construct(
        public int $warehouseId,
        public ?UploadedFile $modelPath,
        public ?array $racks,
        public ?array $walls
    ) {}
}
