<?php

namespace App\UseCases\Warehouse\DeleteLayout;

use App\Exceptions\WarehouseHasPlacementsException;
use App\Exceptions\WarehouseNotFoundException;
use App\Repositories\RackRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteWarehouseLayout
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
        private readonly RackRepository $rackRepository,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(DeleteWarehouseLayoutInput $input): void
    {
        $warehouse = $this->warehouseRepository->getById($input->warehouseId);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        $modelPath = '';

        DB::transaction(function () use ($warehouse, &$modelPath): void {
            $warehouse = $this->warehouseRepository->getByIdForUpdate($warehouse->id);

            if ($warehouse === null) {
                throw new WarehouseNotFoundException;
            }

            if ($this->warehouseRepository->hasPlacements($warehouse->id)) {
                throw new WarehouseHasPlacementsException;
            }

            $modelPath = $warehouse->model_path;

            $this->warehouseRepository->deleteLayout($warehouse);

            $this->rackRepository->deleteLayoutRack($warehouse->id);
        });

        if ($modelPath !== null) {
            Storage::disk('public')->delete($modelPath);
        }
    }
}
