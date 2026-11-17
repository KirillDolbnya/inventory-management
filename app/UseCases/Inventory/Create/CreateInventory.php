<?php

namespace App\UseCases\Inventory\Create;

use App\Exceptions\CannotPlaceToDeletedWarehouseException;
use App\Exceptions\CellNotFoundException;
use App\Exceptions\InventoryNameAlreadyTakenException;
use App\Repositories\CellRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\WarehouseRepository;
use Illuminate\Support\Facades\DB;

class CreateInventory
{
    public function __construct(
        private readonly InventoryRepository $inventoryRepository,
        private readonly CellRepository $cellRepository,
        private readonly WarehouseRepository $warehouseRepository,
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
                $cell = $this->cellRepository->getById($input->cellId);

                if ($cell === null) {
                    throw new CellNotFoundException;
                }

                $rack = $cell->rack;

                $warehouse = $this->warehouseRepository->getByIdForUpdate($rack->warehouse_id);

                if ($warehouse === null) {
                    throw new CannotPlaceToDeletedWarehouseException;
                }

                $this->inventoryRepository->assignToCell($input->cellId, $inventory);
            }

            return $inventory;
        });

        return new CreateInventoryOutput($inventory->id, $inventory->name, $inventory->description, $inventory->quantity);
    }
}
