<?php

namespace App\Repositories;

use App\Models\Cell;
use App\Models\Rack;
use App\UseCases\Rack\Create\CreateRackItemInput;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RackRepository
{
    /**
     * @param  array<int, CreateRackItemInput>  $rackInputs
     * @return array<int, Rack>
     *
     * @throws \Throwable
     */
    public function createRacks(int $warehouseId, array $rackInputs): array
    {
        $createdRacks = [];

        DB::transaction(function () use ($warehouseId, $rackInputs, &$createdRacks) {
            foreach ($rackInputs as $rackInput) {
                $rack = Rack::query()->create([
                    'code' => $rackInput->code,
                    'warehouse_id' => $warehouseId,
                    'levels_count' => $rackInput->levelsCount,
                    'cells_per_level' => $rackInput->cellsPerLevel,
                ]);

                $createdRacks[] = $rack;
            }
        });

        return $createdRacks;
    }

    /**
     * @param  array<int, array{number: int, rack_id: int, level: int, position: int}>  $cellAttributes
     * @return array<int, Cell>
     *
     * @throws \Throwable
     */
    public function createCells(array $cellAttributes): array
    {
        $createdCells = [];

        DB::transaction(function () use ($cellAttributes, &$createdCells) {
            foreach ($cellAttributes as $cell) {
                $createdCell = Cell::query()->create([
                    'number' => $cell['number'],
                    'rack_id' => $cell['rack_id'],
                    'level' => $cell['level'],
                    'position' => $cell['position'],
                ]);

                $createdCells[] = $createdCell;
            }
        });

        return $createdCells;
    }

    /**
     * @param  array<int, int>  $rackIds
     * @return Collection<int, Rack>
     */
    public function getByIdsWithCells(array $rackIds): Collection
    {
        return Rack::query()->whereIn('id', $rackIds)->orderBy('id', 'asc')->with(['cells' => fn ($query) => $query->orderBy('number', 'asc')])->get();
    }

    /**
     * @param  array<int, string>  $codes
     * @return array<int, string>
     */
    public function findExistingCodes(int $warehouseId, array $codes): array
    {
        return Rack::query()->where(['warehouse_id' => $warehouseId])->whereIn('code', $codes)->pluck('code')->all();
    }
}
