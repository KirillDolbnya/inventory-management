<?php

namespace App\UseCases\Rack\Create;

use App\Exceptions\RackCodeAlreadyTakenException;
use App\Exceptions\WarehouseNotFoundException;
use App\Models\Rack;
use App\Repositories\RackRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Facades\DB;

class CreateRacks
{
    public function __construct(
        private readonly RackRepository $rackRepository,
        private readonly WarehouseRepository $warehouseRepository
    ) {}

    /**
     * @throws WarehouseNotFoundException
     * @throws RackCodeAlreadyTakenException
     * @throws \Throwable
     */
    public function __invoke(CreateRacksInput $input): CreateRacksOutput
    {
        $warehouse = $this->warehouseRepository->getById($input->warehouseId);

        if ($warehouse === null) {
            throw new WarehouseNotFoundException;
        }

        $this->ensureRackCodesAreAvailable($warehouse->id, $input->items);

        return DB::transaction(function () use ($warehouse, $input) {
            $racks = $this->rackRepository->createRacks($warehouse->id, $input->items);
            $this->rackRepository->createCells($this->buildCellAttributes($racks));

            $rackIds = array_map(fn ($rack) => $rack->id, $racks);

            $racksWithCells = $this->rackRepository->getByIdsWithCells($rackIds);

            $rackOutputs = $racksWithCells->map(fn (Rack $rack) => new CreateRackItemOutput(
                $rack->id,
                $rack->code,
                $rack->levels_count,
                $rack->cells_per_level,
                $rack->cells->all()
            ))->all();

            return new CreateRacksOutput($rackOutputs);
        });
    }

    /**
     * @param  array<int, Rack>  $racks
     * @return array<int, array{number: int, rack_id: int, level: int, position: int}>
     */
    private function buildCellAttributes(array $racks): array
    {
        $cells = [];

        foreach ($racks as $rack) {
            $cellNumber = 1;
            for ($level = 1; $level <= $rack->levels_count; $level++) {
                for ($position = 1; $position <= $rack->cells_per_level; $position++) {
                    $cells[] = ['number' => $cellNumber++, 'rack_id' => $rack->id, 'level' => $level, 'position' => $position];
                }
            }
        }

        return $cells;
    }

    /**
     * @param  array<int, CreateRackItemInput>  $rackInputs
     *
     * @throws RackCodeAlreadyTakenException
     */
    private function ensureRackCodesAreAvailable(int $warehouseId, array $rackInputs): void
    {
        $rackCodes = array_map(fn (CreateRackItemInput $rackInput) => $rackInput->code, $rackInputs);

        $existingCodes = $this->rackRepository->findExistingCodes($warehouseId, $rackCodes);

        if (! empty($existingCodes)) {
            throw new RackCodeAlreadyTakenException(codesConflicts: $existingCodes);
        }
    }
}
