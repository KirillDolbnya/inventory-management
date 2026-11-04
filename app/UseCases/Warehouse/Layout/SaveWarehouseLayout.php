<?php

namespace App\UseCases\Warehouse\Layout;

use App\Exceptions\MissingWarehouseRacksException;
use App\Exceptions\RackNotFoundException;
use App\Exceptions\WarehouseNotFoundException;
use App\Models\Rack;
use App\Repositories\RackRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class SaveWarehouseLayout
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
        private readonly RackRepository $rackRepository,
    ) {}

    /**
     * @throws \Throwable
     */
    public function __invoke(SaveWarehouseLayoutInput $input): SaveWarehouseLayoutOutput
    {

        $newModelPath = $input->fileModel->store('models', 'public');

        $oldModelPath = null;

        try {
            $output = DB::transaction(function () use ($input, $newModelPath, &$oldModelPath) {
                $warehouse = $this->warehouseRepository->getById($input->warehouseId);

                if ($warehouse === null) {
                    throw new WarehouseNotFoundException;
                }

                $rackIds = collect($input->racks)->map(function (SaveWarehouseLayoutRackInput $rack) {
                    return $rack->rackId;
                })->all();

                $racks = $this->rackRepository->getWarehouseRacksWithoutCells($warehouse->id);

                $notFoundRackIds = array_diff($rackIds, $racks->pluck('id')->all());
                if (! empty($notFoundRackIds)) {
                    throw new RackNotFoundException('Стеллажи с переданными ID не найдены '.implode(', ', $notFoundRackIds));
                }

                $missingRackIds = array_diff($racks->pluck('id')->all(), $rackIds);
                if (! empty($missingRackIds)) {
                    throw new MissingWarehouseRacksException(errors: 'Не передано расположение стеллажей: '.implode(', ', $missingRackIds).'.');
                }

                $recordsToUpdate = collect($input->racks)->map(function (SaveWarehouseLayoutRackInput $rackInput) use ($racks) {

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

                $oldModelPath = $warehouse->model_path;

                $warehouse = $this->warehouseRepository->saveModelFileWithJson($warehouse, $newModelPath, $input->walls);

                $this->rackRepository->savePositionsRack($recordsToUpdate);

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

                    return new SaveWarehouseLayoutRackOutput(
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

                return new SaveWarehouseLayoutOutput(
                    $warehouse->id,
                    $warehouse->walls,
                    $model,
                    $rackOutputs
                );
            });
        } catch (\Throwable $exception) {
            Storage::disk('public')->delete($newModelPath);

            throw $exception;
        }

        if ($oldModelPath !== null && ! empty(trim($oldModelPath)) && $oldModelPath !== $newModelPath) {
            if (Storage::disk('public')->exists($oldModelPath)) {
                Storage::disk('public')->delete($oldModelPath);
            }
        }

        return $output;
    }
}
