<?php

namespace App\UseCases\Rack\Update;

use App\Exceptions\RackCodeAlreadyTakenException;
use App\Exceptions\RackHasPlacementsException;
use App\Exceptions\RackNotFoundException;
use App\Exceptions\WarehouseNotFoundException;
use App\Models\Cell;
use App\Models\InventoryPlacement;
use App\Repositories\CellRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\RackRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UpdateRack
{
    public function __construct(
        private readonly WarehouseRepository $warehouseRepository,
        private readonly RackRepository $rackRepository,
        private readonly CellRepository $cellRepository,
        private readonly InventoryRepository $inventoryRepository,
    ) {}

    /**
     * @throws WarehouseNotFoundException
     * @throws RackNotFoundException
     * @throws \Throwable
     */
    public function __invoke(UpdateRackInput $input): UpdateRackOutput
    {
        $warehouse = $this->warehouseRepository->getById($input->warehouseId);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        $rack = $this->rackRepository->getByRackIdWithWarehouseId($input->warehouseId, $input->rackId);

        if ($rack === null) {
            throw new RackNotFoundException;
        }

        return DB::transaction(function () use ($input, $rack, $warehouse) {
            $warehouse = $this->warehouseRepository->getByIdForUpdate($warehouse->id);

            if ($warehouse === null) {
                throw new WarehouseNotFoundException;
            }

            $rack = $this->rackRepository->getByRackIdWithWarehouseId($warehouse->id, $rack->id);

            if ($rack === null) {
                throw new RackNotFoundException;
            }

            if ($input->code !== null) {
                $existingCodes = $this->rackRepository->findExistingCodes($warehouse->id, [$input->code], $rack->id);

                if (! empty($existingCodes)) {
                    throw new RackCodeAlreadyTakenException(codesConflicts: $existingCodes);
                }
            }

            $cells = $this->cellRepository->getByRackIdWithTrashed($rack->id);
            $cellIds = $cells->pluck('id')->toArray();

            $placements = $this->inventoryRepository->getPlacementsByCellIds($cellIds);

            $levelsCount = $input->levelsCount ? $input->levelsCount : $rack->levels_count;
            $cellsPerLevel = $input->cellsPerLevel ? $input->cellsPerLevel : $rack->cells_per_level;

            if ($input->levelsCount !== null && $input->levelsCount < $rack->levels_count || $input->cellsPerLevel !== null && $input->cellsPerLevel < $rack->cells_per_level) {
                $cellsPayload = $this->cellsToDelete($levelsCount, $cellsPerLevel, $cells, $placements);

                if (! empty($cellsPayload['deleted'])) {
                    $this->cellRepository->deleteCells($cellsPayload['deleted']);
                }

                if (! empty($cellsPayload['updated'])) {
                    if ($input->cellsPerLevel > $rack->cells_per_level) {
                        usort($cellsPayload['updated'], fn (array $a, array $b) => $b['number'] <=> $a['number']);
                    } elseif ($input->cellsPerLevel < $rack->cells_per_level) {
                        usort($cellsPayload['updated'], fn (array $a, array $b) => $a['number'] <=> $b['number']);
                    }

                    $this->cellRepository->updateCells($cellsPayload['updated']);
                }
            }

            if ($input->levelsCount !== null && $input->levelsCount > $rack->levels_count || $input->cellsPerLevel !== null && $input->cellsPerLevel > $rack->cells_per_level) {
                $cellsPayload = $this->cellsToCreate($levelsCount, $cellsPerLevel, $cells, $rack->id);

                if (! empty($cellsPayload['updated'])) {
                    usort($cellsPayload['updated'], fn (array $a, array $b) => $b['number'] <=> $a['number']);
                    $this->cellRepository->updateCells($cellsPayload['updated']);
                }

                if (! empty($cellsPayload['created'])) {
                    $this->cellRepository->createCells($cellsPayload['created']);
                }
            }

            $rackUpdateData = array_filter([
                'code' => $input->code,
                'levels_count' => $input->levelsCount,
                'cells_per_level' => $input->cellsPerLevel,
            ], fn ($value) => $value !== null);

            $rack = $this->rackRepository->update($rack, $rackUpdateData);
            $cells = $this->cellRepository->getByRackId($rack->id);

            $cellsOutput = $cells->map(fn ($cell) => [
                'id' => $cell->id,
                'number' => $cell->number,
                'level' => $cell->level,
                'position' => $cell->position,
            ])->all();

            return new UpdateRackOutput($rack->id, $rack->code, $rack->levels_count, $rack->cells_per_level, $cellsOutput);
        });
    }

    /**
     * @param  Collection<int, Cell>  $cells
     * @param  Collection<int, InventoryPlacement>  $placements
     * @return array{
     *     deleted: list<int>,
     *     updated: list<array{
     *         id: int,
     *         rack_id: int,
     *         number: int,
     *         level: int,
     *         position: int,
     *         deleted_at: null
     *     }>
     * }
     *
     * @throws RackHasPlacementsException
     */
    private function cellsToDelete(int $levelsCount, int $cellsPerLevel, Collection $cells, Collection $placements): array
    {
        $cellsPayload = [
            'deleted' => [],
            'updated' => [],
        ];

        $cellsDeleteLevel = $cells->filter(fn (Cell $cell) => ! $cell->trashed())->where('level', '>', $levelsCount);
        $cellsDeletePosition = $cells->filter(fn (Cell $cell) => ! $cell->trashed())->where('position', '>', $cellsPerLevel);

        $cellIdsToDelete = $cellsDeleteLevel->merge($cellsDeletePosition)->pluck('id')->all();

        $cellsPlacements = $placements->whereIn('cell_id', $cellIdsToDelete)->pluck('id');

        if ($cellsPlacements->isNotEmpty()) {
            throw new RackHasPlacementsException('Нельзя изменить стеллаж, так как в нем есть размещения предмета.');
        }

        $cellNumber = 1;
        for ($level = 1; $level <= $levelsCount; $level++) {
            for ($position = 1; $position <= $cellsPerLevel; $position++) {
                $cellsAtCoordinate = $cells->where('level', $level)->where('position', $position);
                $cell = $cellsAtCoordinate->first(fn (Cell $cell) => ! $cell->trashed()) ?? $cellsAtCoordinate->first(fn (Cell $cell) => $cell->trashed());

                if ($cell !== null) {
                    $cellsPayload['updated'][] = ['id' => $cell->id, 'number' => $cellNumber, 'rack_id' => $cell->rack_id, 'level' => $level, 'position' => $position, 'deleted_at' => null];
                }

                $cellNumber++;
            }
        }

        $cellsPayload['deleted'] = $cellIdsToDelete;

        return $cellsPayload;
    }

    /**
     * @param  Collection<int, Cell>  $cells
     * @return array{
     *     created?: list<array{
     *         number: int,
     *         rack_id: int,
     *         level: int,
     *         position: int
     *     }>,
     *     updated?: list<array{
     *         id: int,
     *         rack_id: int,
     *         number: int,
     *         level: int,
     *         position: int,
     *         deleted_at: null,
     *     }>
     * }
     */
    private function cellsToCreate(int $levelsCount, int $cellsPerLevel, Collection $cells, int $rackId): array
    {
        $cellsPayload = [
            'created' => [],
            'updated' => [],
        ];
        $cellNumber = 1;

        for ($level = 1; $level <= $levelsCount; $level++) {
            for ($position = 1; $position <= $cellsPerLevel; $position++) {
                $cellsAtCoordinate = $cells->where('level', $level)->where('position', $position);
                $cell = $cellsAtCoordinate->first(fn (Cell $cell) => ! $cell->trashed()) ?? $cellsAtCoordinate->first(fn (Cell $cell) => $cell->trashed());

                if ($cell === null) {
                    $cellsPayload['created'][] = ['number' => $cellNumber, 'rack_id' => $rackId, 'level' => $level, 'position' => $position];
                } else {
                    $cellsPayload['updated'][] = ['id' => $cell->id, 'number' => $cellNumber, 'rack_id' => $rackId, 'level' => $level, 'position' => $position, 'deleted_at' => null];
                }

                $cellNumber++;
            }
        }

        return $cellsPayload;
    }
}
