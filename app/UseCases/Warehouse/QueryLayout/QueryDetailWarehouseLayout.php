<?php

namespace App\UseCases\Warehouse\QueryLayout;

use App\Exceptions\WarehouseLayoutNotFoundException;
use App\Exceptions\WarehouseNotFoundException;
use App\Models\Rack;
use App\Repositories\RackRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Facades\Storage;

class QueryDetailWarehouseLayout
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
        private readonly RackRepository $rackRepository
    ) {}

    /**
     * @throws WarehouseNotFoundException
     * @throws WarehouseLayoutNotFoundException
     */
    public function __invoke(QueryDetailWarehouseLayoutInput $input): QueryDetailWarehouseLayoutOutput
    {
        $warehouse = $this->warehouseRepository->getById($input->warehouseId);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        if ($warehouse->model_path === null || empty(trim($warehouse->model_path))) {
            throw new WarehouseLayoutNotFoundException;
        }

        $racks = $this->rackRepository->getWarehouseRacksWithoutCells($warehouse->id);

        $hasUnconfiguredRack = $racks->contains(function (Rack $rack) {
            return is_null($rack->position_x)
                || is_null($rack->position_y)
                || is_null($rack->position_z)
                || is_null($rack->rotation_y_deg)
                || is_null($rack->width)
                || is_null($rack->depth)
                || is_null($rack->height);
        });

        if ($hasUnconfiguredRack) {
            throw new WarehouseLayoutNotFoundException;
        }

        $model = [
            'url' => Storage::disk('public')->url($warehouse->model_path),
            'format' => $warehouse::FORMAT,
            'unit' => $warehouse::UNIT,
            'origin' => $warehouse::ORIGIN,
        ];

        $rackOutputs = $racks->map(function (Rack $rack) {
            $positions = [
                'x' => (float) $rack->position_x,
                'y' => (float) $rack->position_y,
                'z' => (float) $rack->position_z,
            ];

            $dimensions = [
                'width' => (float) $rack->width,
                'depth' => (float) $rack->depth,
                'height' => (float) $rack->height,
            ];

            return new QueryDetailWarehouseLayoutRackOutput(
                $rack->id,
                $rack->code,
                $positions,
                (float) $rack->rotation_y_deg,
                $dimensions,
                $rack->levels_count,
                $rack->cells_per_level
            );
        })->all();

        return new QueryDetailWarehouseLayoutOutput($warehouse->id, $warehouse->walls, $model, $rackOutputs);
    }
}
