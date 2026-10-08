<?php

namespace App\UseCases\Inventory\Create;

use App\Exceptions\CellNotFoundException;
use App\Exceptions\InventoryNameAlreadyTakenException;
use App\Repositories\CellRepository;
use App\Repositories\InventoryRepository;
use Illuminate\Support\Facades\DB;

class CreateInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly CellRepository $cellRepository,
    ) {}

    /**
     * @throws InventoryNameAlreadyTakenException
     * @throws CellNotFoundException
     * @throws \Throwable
     */
    public function __invoke(CreateInventoryInput $input): CreateInventoryOutput
    {
        if ($this->inventoryRepository->existsByName($input->name)) {
            throw new InventoryNameAlreadyTakenException;
        }

        $inventory = DB::transaction(function () use ($input) {
            $inventory = $this->inventoryRepository->create($input->name, $input->description, $input->quantity);

            if ($input->cellId !== null) {
                $this->checkCellId($input->cellId);

                $this->inventoryRepository->assignToCell($input->cellId, $inventory);
            }

            return $inventory;
        });

        return new CreateInventoryOutput($inventory->id, $inventory->name, $inventory->description, $inventory->quantity);
    }

    /**
     * @throws CellNotFoundException
     */
    private function checkCellId(int $cellId): void
    {
        if (! $this->cellRepository->cellExistsById($cellId)) {
            throw new CellNotFoundException;
        }
    }
}
