<?php

namespace App\UseCases\Warehouse\UpdateLayout;

use App\Exceptions\RackNotFoundException;
use App\Exceptions\WarehouseLayoutNotFoundException;
use App\Exceptions\WarehouseModelUploadFailedException;
use App\Exceptions\WarehouseNotFoundException;
use App\Models\Rack;
use App\Repositories\RackRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UpdateLayout
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
        private readonly RackRepository $rackRepository,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(UpdateLayoutInput $input): UpdateLayoutOutput
    {
        $warehouse = $this->warehouseRepository->getById($input->warehouseId);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        $actualModelPath = null;
        $newModelPath = $input->modelPath?->store('models', 'public');

        if ($newModelPath === false) {
            throw new WarehouseModelUploadFailedException($warehouse->id);
        }

        try {
            $output = DB::transaction(function () use ($warehouse, $input, &$newModelPath, &$actualModelPath) {
                $warehouse = $this->warehouseRepository->getByIdForUpdate($warehouse->id);

                if ($warehouse === null) {
                    throw new WarehouseNotFoundException;
                }

                if ($warehouse->model_path === null || empty(trim($warehouse->model_path))) {
                    throw new WarehouseLayoutNotFoundException;
                }

                $recordsToUpdate = null;

                if ($input->racks !== null) {
                    $rackIds = collect($input->racks)->map(function (UpdateLayoutRackInput $rack) {
                        return $rack->rackId;
                    })->all();

                    $racks = $this->rackRepository->getWarehouseRacksWithoutCells($warehouse->id);

                    $notFoundRackIds = array_diff($rackIds, $racks->pluck('id')->all());
                    if (! empty($notFoundRackIds)) {
                        throw new RackNotFoundException('Стеллажи с переданными ID не найдены '.implode(', ', $notFoundRackIds));
                    }

                    $recordsToUpdate = collect($input->racks)->map(function (UpdateLayoutRackInput $rackInput) use ($racks) {
                        $rack = $racks->where('id', $rackInput->rackId)->first();

                        return [
                            'id' => $rack->id,
                            'code' => $rack->code,
                            'warehouse_id' => $rack->warehouse_id,
                            'levels_count' => $rack->levels_count,
                            'cells_per_level' => $rack->cells_per_level,
                            'position_x' => $rackInput->positionX,
                            'position_y' => $rackInput->positionY,
                            'position_z' => $rackInput->positionZ,
                            'rotation_y_deg' => $rackInput->rotationYDeg,
                            'width' => $rackInput->width,
                            'depth' => $rackInput->depth,
                            'height' => $rackInput->height,
                        ];
                    })->all();
                }

                $actualModelPath = $warehouse->model_path;

                if ($newModelPath !== null || $input->walls !== null) {
                    $updateWarehouseData = array_filter([
                        'model_path' => $newModelPath,
                        'walls' => $input->walls,
                    ]);

                    $warehouse = $this->warehouseRepository->saveModelFileWithJson($warehouse, $updateWarehouseData);
                }

                if ($recordsToUpdate !== null) {
                    $this->rackRepository->savePositionsRack($recordsToUpdate);
                }

                $racks = $this->rackRepository->getWarehouseRacksWithoutCells($warehouse->id);

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

                    return new UpdateLayoutRackOutput(
                        $rack->id,
                        $rack->code,
                        $positions,
                        (float) $rack->rotation_y_deg,
                        $dimensions,
                        $rack->levels_count,
                        $rack->cells_per_level
                    );
                })->all();

                $model = [
                    'url' => Storage::disk('public')->url($warehouse->model_path),
                    'format' => $warehouse::FORMAT,
                    'unit' => $warehouse::UNIT,
                    'origin' => $warehouse::ORIGIN,
                ];

                return new UpdateLayoutOutput(
                    $warehouse->id,
                    $warehouse->walls,
                    $model,
                    $rackOutputs
                );
            });
        } catch (\Throwable $exception) {
            if ($newModelPath !== null) {
                Storage::disk('public')->delete($newModelPath);
            }

            throw $exception;
        }

        if ($actualModelPath !== null && $newModelPath !== null && ! empty(trim($actualModelPath)) && $actualModelPath !== $newModelPath) {
            if (Storage::disk('public')->exists($actualModelPath)) {
                Storage::disk('public')->delete($actualModelPath);
            }
        }

        return $output;
    }
}
